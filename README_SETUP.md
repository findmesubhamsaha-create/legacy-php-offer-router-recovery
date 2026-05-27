# Setup

1. Clone repo

git clone ...
git checkout analytics-product-v2

2. Start containers

docker compose up -d

3. Copy env

cp .env.example .env

4. Import DB

docker compose exec -T db mysql -uroot -prootpassword efbhalvbhdsurl < database/database_seed.sql

5. Open

http://localhost:8080