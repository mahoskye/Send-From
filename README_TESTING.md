Testing the Send From plugin locally with Docker

This repository contains the legacy WordPress plugin `Send From` (main file `send-from.php`).

What this adds
- A Docker Compose configuration (`docker-compose.yml`) that spins up WordPress + MySQL and mounts this project into the plugins directory so you can activate and test the plugin.

Quick steps (PowerShell / pwsh on Windows):

1. Install Docker Desktop and enable WSL2/integration as required by Docker for Windows.
2. From this repository root, start the stack:

```powershell
# from project root (where docker-compose.yml lives)
docker compose up -d
```

3. Wait ~30 seconds for containers to initialize, then open http://localhost:8000 in your browser and complete the WordPress setup (site title, admin user).
4. In the WP Admin, go to Plugins and activate "Send From". Then go to Settings -> Send From to exercise the plugin UI, change the From name/email and test the "Send Test" feature.

Notes
- The stack exposes WordPress on port 8000 and uses a MySQL container with credentials configured in `docker-compose.yml` (db user: `wp`, password: `wp`).
- The project is mounted read-only into the plugins folder by default. If you want to edit files from the running container (not recommended for tests), change the volume to read-write in `docker-compose.yml`.
- If you don't have `docker compose` command available, `docker-compose up -d` (legacy) may work depending on your Docker installation.

Security
- This environment is for local testing only. Do not expose it to the public internet.

Cleaning up

```powershell
docker compose down -v
```
