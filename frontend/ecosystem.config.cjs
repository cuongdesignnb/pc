module.exports = {
  apps: [
    {
      name: "pcfrontend",
      port: 3001,
      exec_mode: "fork",
      script: "./.output/server/index.mjs",
      env: {
        PORT: 3001,
        HOST: "0.0.0.0",
        NODE_ENV: "production",
      },
    },
  ],
};
