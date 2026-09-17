FROM python:3.12-slim

WORKDIR /app

# Отключаем буферизацию вывода python
ENV PYTHONUNBUFFERED=1

# Генерация локалей UTF-8
RUN apt-get update && apt-get install -y --no-install-recommends locales \
    && sed -i -e 's/# ru_RU.UTF-8 UTF-8/ru_RU.UTF-8 UTF-8/' /etc/locale.gen \
    && sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen \
    && dpkg-reconfigure --frontend=noninteractive locales \
    && rm -rf /var/lib/apt/lists/*

ENV LANG=ru_RU.UTF-8 \
    LC_ALL=ru_RU.UTF-8

# Установка зависимостей
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Копирование исходного кода бота
COPY . .

CMD ["python", "main.py"]
