FROM python:3.12-slim

WORKDIR /app

# Отключаем буферизацию вывода python
ENV PYTHONUNBUFFERED=1

# Установка зависимостей
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Копирование исходного кода бота
COPY . .

CMD ["python", "main.py"]
