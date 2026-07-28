cd backend
composer install
cp .env.example .env
php artisan key:generate

php artisan migrate
php artisan db:seed

php artisan serve

cd ../frontend
npm install
npm run dev

githup
AWS EC2
