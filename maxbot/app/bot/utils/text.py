import re

_TAG_RE = re.compile(r"<[^>]+>")


def strip_html(text: str) -> str:
    """Удаляет HTML теги из строки."""
    if not text:
        return ""
    return _TAG_RE.sub("", text)
