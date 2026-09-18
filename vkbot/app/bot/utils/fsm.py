from typing import Any

from vkbottle import BaseStateGroup
from vkbottle.dispatch.dispenser.base import get_state_repr

from app.bot.loader import state_dispenser
from app.bot.states import Idle


def _to_state_str(state: "BaseStateGroup | str") -> str:
    """
    Приводит состояние к обычной str ДО того, как оно попадёт в
    state_dispenser.set().

    Если передать туда "сырой" BaseStateGroup, vkbottle сам обернёт его во
    внутренний класс StateRepresentation (см. vkbottle/dispatch/dispenser/base.py).
    В некоторых версиях vkbottle этот класс не хешируется (переопределён
    __eq__ без __hash__), из-за чего падает любое сравнение "state in {...}".
    Поэтому мы всегда заранее превращаем состояние в обычную str — тогда
    pydantic-валидатор StatePeer просто сохраняет её как есть, без обёртки.
    """
    if isinstance(state, str) and not isinstance(state, BaseStateGroup):
        return state
    if isinstance(state, dict):
        return "Idle:none"
    return get_state_repr(state)


async def get_state_data(peer_id: int) -> dict[str, Any]:
    """Аналог await state.get_data() из aiogram."""
    peer = await state_dispenser.get(peer_id)
    if peer is None:
        return {}
    return dict(peer.payload)


async def get_current_state(peer_id: int) -> str | None:
    """Возвращает строковое представление текущего состояния (или None)."""
    peer = await state_dispenser.get(peer_id)
    if peer is None:
        return None
    return str(peer.state)


async def update_state_data(peer_id: int, state: "BaseStateGroup | str | None | dict" = None, **new_data: Any) -> None:
    """
    Аналог await state.update_data(**kwargs), с опциональной сменой состояния
    (аналог await state.set_state(...), если он делается одновременно).

    В vkbottle .set() перезаписывает payload целиком, поэтому здесь мы
    сначала подмешиваем уже сохранённые данные.
    """
    peer = await state_dispenser.get(peer_id)
    data = dict(peer.payload) if peer is not None else {}

    if isinstance(state, dict):
        data.update(state)
        state = peer.state if peer is not None else Idle.none

    data.update(new_data)

    if state is None:
        state = peer.state if peer is not None else Idle.none

    await state_dispenser.set(peer_id, _to_state_str(state), **data)


async def state_in(peer_id: int, *states: "BaseStateGroup") -> bool:
    """
    Проверка текущего состояния для raw_event (message_event) хендлеров.

    В отличие от обычных сообщений, событиям нажатия callback-кнопок
    (message_event) vkbottle НЕ проставляет .state_peer автоматически
    (это особенность ABCRawEventView), поэтому StateRule там не работает.
    Этот хелпер — ручная замена StateRule для таких хендлеров.
    """
    current = await get_current_state(peer_id)
    if current is None:
        return False
    wanted = {get_state_repr(s) for s in states}
    return current in wanted


async def set_state(peer_id: int, state: "BaseStateGroup | str") -> None:
    """Аналог await state.set_state(...), сохраняя уже имеющиеся данные."""
    await update_state_data(peer_id, state=state)


async def clear_state(peer_id: int, keep: tuple[str, ...] = ("lang",)) -> None:
    """
    Аналог await state.clear(). По умолчанию сохраняет ключ 'lang' —
    в оригинальном tg-боте после state.clear() почти везде сразу же
    делают state.update_data(lang=lang), чтобы не терять выбранный язык.
    """
    peer = await state_dispenser.get(peer_id)
    data = dict(peer.payload) if peer is not None else {}
    kept = {k: data[k] for k in keep if k in data}
    await state_dispenser.set(peer_id, _to_state_str(Idle.none), **kept)
