# Docker-Umgebung

## Inhaltsverzeichnis
- [Voraussetzungen](#voraussetzungen)
- [Installation von Docker auf Windows](#installation-von-docker-auf-windows)
- [Projektverzeichnis anlegen](#projektverzeichnis-anlegen)
- [Docker Compose verwenden](#docker-compose-verwenden)
- [Nützliche Docker-Befehle](#nützliche-docker-befehle)

## Voraussetzungen
- Windows 10 oder höher
- [Docker Desktop](https://www.docker.com/products/docker-desktop)
- Git (optional, falls Repository verwendet wird)

## Installation von Docker auf Windows
### 1. Docker Desktop herunterladen
- Gehe zur [Docker Desktop Website](https://www.docker.com/products/docker-desktop) und lade die **Docker Desktop für Windows** Version herunter.

### 2. Docker Desktop installieren
- Führe die heruntergeladene `.exe`-Datei aus und folge den Installationsanweisungen.
- Stelle sicher, dass die Option "Use WSL 2 instead of Hyper-V" aktiviert ist (WSL 2 ist leistungsfähiger und empfohlen).

### 3. Docker Desktop starten
- Nach der Installation öffne Docker Desktop und starte es. Du solltest eine Benachrichtigung sehen, dass Docker erfolgreich läuft.

### 4. Überprüfen der Installation
- Öffne ein Terminal (Powershell oder Eingabeaufforderung) und führe den folgenden Befehl aus, um zu überprüfen, ob Docker korrekt installiert wurde:

    ```bash
    docker --version
    ```

    Die Ausgabe sollte die installierte Docker-Version anzeigen.

## Projektverzeichnis anlegen
### 1. Erstelle ein Projektverzeichnis
- Erstelle ein Verzeichnis für dein Projekt:

    ```bash
    mkdir mein-docker-projekt
    cd mein-docker-projekt
    ```

### 2. Kopiere alle Dateien aus diesem Verzeichnis in dein Projektverzeichnis:

### 3. Konfiguriere den Container:
- im File `docker-compose.yaml` sind alle Einstallungen zu finden (z.B. für die Ports)

## Docker Compose verwenden
### 1. Docker Compose Build ausführen
- Um das Docker-Image zu erstellen, führe den folgenden Befehl im Projektverzeichnis aus:

    ```bash
    docker-compose build
    ```

### 2. Container starten
- Nachdem das Build abgeschlossen ist, starte den Container mit:

    ```bash
    docker-compose up
    ```

- Der Container sollte nun laufen, und du kannst die Anwendung über `http://localhost:5000` in deinem Browser aufrufen.

### 3. Container im Hintergrund (optional)
- Um den Container im Hintergrund auszuführen, verwende:

    ```bash
    docker-compose up -d
    ```

## Nützliche Docker-Befehle
- **Container stoppen**:

    ```bash
    docker-compose down
    ```

- **Container und Volumes entfernen**:

    ```bash
    docker-compose down --volumes
    ```

- **Logs anzeigen**:

    ```bash
    docker-compose logs
    ```

- **Neu bauen** (nach Änderungen am `Dockerfile` oder der Anwendung):

    ```bash
    docker-compose up --build
    ```

## Weiterführende Links
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Dockerfile Best Practices](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)

# Software Settings

MySQL Settings:
im `.env` File findet man die Konfiguration der Datenbank (Username, Password,...)