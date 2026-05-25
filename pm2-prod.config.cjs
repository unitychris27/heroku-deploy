module.exports = {
  apps: [{
    name: "bot-deploy-api",
    cwd: "/home/tipmrnhl/herokudeploy/artifacts/api-server",
    script: "dist/index.mjs",
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: "256M",
    env: {
      NODE_ENV: "production",
      PORT: 8097,
    },
    error_file: "/home/tipmrnhl/herokudeploy/artifacts/api-server/logs/error.log",
    out_file: "/home/tipmrnhl/herokudeploy/artifacts/api-server/logs/out.log",
    log_date_format: "YYYY-MM-DD HH:mm:ss",
  }],
};
