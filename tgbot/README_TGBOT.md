# Инструкция по запуску Telegram-бота на зарубежном сервере

Данный бот (`tgbot`) запускается на зарубежном VPS / сервере (Германия, Финляндия, Нидерланды и т.д.), чтобы иметь стабильный доступ к Telegram API без блокировок.

---

## 1. Как бот работает с базой данных на российском сервере?

База данных **PostgreSQL** расположена на вашем российском сервере (например, в Москве).
PostgreSQL работает по стандартному сетевому TCP-протоколу. Бот подключается к ней напрямую через интернет, как к обычному сетевому сервису.

### Что требуется сделать на российском сервере (в Москве):
1. **Открыть порт 5432** в файрволе сервера.
   Рекомендуется разрешить входящие подключения к порту 5432 **только** с IP-адреса зарубежного сервера (чтобы обезопасить базу от ботов-сканеров):
   ```bash
   sudo ufw allow from <IP_ЗАРУБЕЖНОГО_СЕРВЕРА> to any port 5432
   ```
2. Убедиться, что в `docker-compose.yml` на российском сервере порт проброшен:
   ```yaml
   ports:
     - "5432:5432"
   ```
3. Использовать надёжный пароль для базы данных в файле `.env` на российском сервере (не оставлять стандартный `postgres`).

---

## 2. Настройка конфигурации на зарубежном сервере

1. В папке с ботом создайте файл `.env` на основе примера:
   ```bash
   cp .env.example .env
   ```

2. Откройте `.env` и укажите данные:
   ```env
   # Токен бота Telegram (полученный у @BotFather)
   BOT_TOKEN=1234567890:ABCDefGhIjKlMnOpQrStUvWxYz

   # IP-адрес или домен вашего российского сервера (в Москве)
   HOST=185.xxx.xxx.xxx
   PORT=5432
   USER=postgres
   PASSWORD=ваш_надежный_пароль
   DBNAME=layndaru_db

   # Строка подключения SQLAlchemy asyncpg:
   DATABASE_URL=postgresql+asyncpg://postgres:ваш_надежный_пароль@185.xxx.xxx.xxx:5432/layndaru_db
   ```

3. **Проверка связи с базой данных** (перед запуском):
   Выполните на зарубежном сервере команду проверки доступности порта базы:
   ```bash
   nc -zv 185.xxx.xxx.xxx 5432
   ```
   Если вывод: `Connection to 185.xxx.xxx.xxx 5432 port [tcp/postgresql] succeeded!` — связь работает отлично!

---

## 3. Запуск бота

### Вариант А: Запуск через Docker (Рекомендуется)
Ничего устанавливать на сервер не нужно, кроме Docker:
```bash
docker compose up -d --build
```
- Посмотреть логи: `docker compose logs -f tgbot`
- Перезапустить: `docker compose restart tgbot`
- Остановить: `docker compose down`

### Вариант Б: Запуск напрямую через Python
Требуется Python 3.10+:
```bash
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
python main.py
```

---

## 4. (Опционально) Настройка systemd службы (если запускаете без Docker)
Создайте файл `/etc/systemd/system/layndaru-tgbot.service`:
```ini
[Unit]
Description=Layndaru Telegram Bot
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/layndaru/tgbot
ExecStart=/opt/layndaru/tgbot/venv/bin/python main.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```
Активируйте службу:
```bash
sudo systemctl daemon-reload
sudo systemctl enable layndaru-tgbot
sudo systemctl start layndaru-tgbot
```
