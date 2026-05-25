module.exports = {
  apps: [
    {
      name: "bot-deploy-api",
      script: "artifacts/bot-deploy-api/dist/index.mjs",
      interpreter: "node",
      interpreter_args: "--enable-source-maps",
      instances: 1,
      exec_mode: "fork",
      watch: false,
      env: {
        NODE_ENV: "production",
        PORT: "8097",
        BASE_PATH: "",
      },
      error_file: "logs/bot-deploy-api.err.log",
      out_file: "logs/bot-deploy-api.out.log",
      log_date_format: "YYYY-MM-DD HH:mm:ss Z",
      max_restarts: 10,
      restart_delay: 3000,
      autorestart: true,
    },
  ],
};
