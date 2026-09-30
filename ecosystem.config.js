/* PM2-конфиг локального стенда nilov-flowers-site.
   Порт 8123: Bun static + content API server (server.js).
   Порт 3000 занят сэндбоксом my-project (Next.js) — НЕ трогаем;
   он даёт превью пользователю через next.config rewrites → 8123.
   Запуск: pm2 start ecosystem.config.js && pm2 save
   Логи:    pm2 logs nilov-site  (файлы в state/pm2-*.log) */
module.exports = {
  apps: [
    {
      name: 'nilov-site',
      script: 'bun',
      args: 'run server.js',
      cwd: '/home/z/nilov-flowers-site',
      watch: false,
      autorestart: true,
      max_restarts: 20,
      restart_delay: 2000,
      out_file: '/home/z/nilov-flowers-site/state/pm2-out.log',
      error_file: '/home/z/nilov-flowers-site/state/pm2-err.log',
      time: true,
    },
  ],
};
