from vkbottle import BaseStateGroup


# --- Запись на стирку ---
class AddRecord(BaseStateGroup):
    waiting_for_machine_type = "waiting_for_machine_type"
    waiting_for_day = "waiting_for_day"
    waiting_for_time = "waiting_for_time"
    waiting_for_machine = "waiting_for_machine"


class Report(BaseStateGroup):
    waiting_for_report = "waiting_for_report"


class CancelRecord(BaseStateGroup):
    waiting_for_cancel = "waiting_for_cancel"


class DisplayRecords(BaseStateGroup):
    waiting_for_display = "waiting_for_display"


# --- Аутентификация ---
class Auth(BaseStateGroup):
    waiting_for_fio = "waiting_for_fio"        # Ждем ФИО для проверки
    waiting_for_id_card = "waiting_for_id_card"  # Если ФИО нет, ждем зачетку


# --- "Пустое" состояние ---
# В vkbottle StatePeer всегда хранит и state, и payload (данные) вместе,
# и .set() перезаписывает их одним вызовом (в отличие от aiogram, где
# state и data живут раздельно). Чтобы не терять сохранённые данные
# (например, выбранный язык) при выходе из сценария, мы не "очищаем"
# состояние, а переводим его в Idle, сохраняя нужные данные (см.
# app/bot/utils/fsm.py -> clear_state()).
class Idle(BaseStateGroup):
    none = "none"
