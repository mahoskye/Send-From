# Start the WordPress test stack using Docker Compose
# Usage: Open PowerShell in the project root and run: .\start-wordpress.ps1

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Error "Docker is not installed or not in PATH. Install Docker Desktop first: https://www.docker.com/products/docker-desktop"
    exit 1
}

Write-Host "Starting WordPress + MySQL containers..."

# Use docker compose if available; fallback to docker-compose
$cmd = if (Get-Command 'docker' -ErrorAction SilentlyContinue) { 'docker compose' } else { 'docker-compose' }

& $cmd up -d

Write-Host "Containers started. WordPress will be available at http://localhost:8000 after initialization (give it ~30s)."
Write-Host "To stop and remove containers: '$cmd down -v'"
