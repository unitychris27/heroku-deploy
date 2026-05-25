module.exports = {
  apps: [{
    name: "heroku-deploy-api",
    cwd: "/opt/herokudeploy/artifacts/api-server",
    script: "dist/index.mjs",
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: "512M",
    env: {
      NODE_ENV: "production",
      PORT: 8097,
    },
    error_file: "/var/log/herokudeploy/api-error.log",
    out_file: "/var/log/herokudeploy/api-out.log",
    log_date_format: "YYYY-MM-DD HH:mm:ss",
  }],
};
