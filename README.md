## LAMP Issue Tracker

Minimal issue tracking application built with PHP, MySQL, and Apache. Designed to live inside a single project folder and run on a single virtual machine.

### Project Structure
- `public/` – Apache document root; contains the web UI (`index.php`).
- `includes/` – PHP includes for configuration and reusable logic.
- `database/schema.sql` – Database schema and bootstrap script.

### Local Development (VS Code + Podman)
1. Install [Podman](https://podman.io/) for your platform.  
   - macOS/Windows: `brew install podman` then run `podman machine init` once and `podman machine start` each session (or enable automatic start).  
   - Linux: install the `podman` and `podman-docker` packages from your distro.
2. Open the folder in VS Code. The Docker extension works with Podman; optionally add `alias docker=podman` in your shell if you prefer Docker-style commands.
3. Start the stack from the VS Code terminal:
   ```bash
   podman compose up --build
   ```
   - `app` service runs Apache + PHP 8.2 from `docker/web/Dockerfile`.
   - `db` service provisions MySQL 8 with the schema seeded from `database/schema.sql`.
4. Browse to [http://localhost:8080](http://localhost:8080) to use the tracker. Hot reloading works because the project folder is bind-mounted into the container.
5. Stop the services when you are done:
   ```bash
   podman compose down
   ```
   Append `--volumes` if you want to remove the MySQL data volume.

> Tip: The environment variables defined in `docker-compose.yml` automatically flow into PHP (via `includes/config.php`), so no extra configuration is required when running under Podman.

### Prerequisites
Run these commands on a fresh Ubuntu/Debian VM (adjust package names for other distros):

```bash
sudo apt update
sudo apt install -y apache2 php php-mysqli mariadb-server
```

Enable Apache’s PHP module (if it is not already):

```bash
sudo a2enmod php*
sudo systemctl restart apache2
```

### Database Setup
1. Secure the MySQL installation if you have not already:
   ```bash
   sudo mysql_secure_installation
   ```
2. Create the database, user, and schema:
   ```bash
   sudo mysql < database/schema.sql
   ```
   The script creates:
   - Database: `lamp_issue_tracker`
   - Table: `issues`
   - Table: `issue_updates`
   - User creation is **not** included; run the snippet below if you want a dedicated application user.
   If you are updating an existing database, run only the new table statement:
   ```sql
   CREATE TABLE IF NOT EXISTS issue_updates (
       id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
       issue_id INT UNSIGNED NOT NULL,
       note TEXT NOT NULL,
       created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
       CONSTRAINT fk_issue_updates_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
       INDEX idx_issue_created (issue_id, created_at)
   );
   ```
3. Optional: create a dedicated user and grant minimal privileges:
   ```sql
   CREATE USER 'lamp_user'@'localhost' IDENTIFIED BY 'lamp_pass';
   GRANT SELECT, INSERT, UPDATE ON lamp_issue_tracker.* TO 'lamp_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Application Configuration
Update `includes/config.php` with the database credentials you prefer. The defaults assume:
- Host: `127.0.0.1`
- Port: `3306`
- Database: `lamp_issue_tracker`
- User: `lamp_user`
- Password: `lamp_pass`

### Apache Configuration
Point Apache’s document root to the `public/` directory inside this project.

```bash
sudo vi /etc/apache2/sites-available/issue-tracker.conf
```

Example virtual host:

```apache
<VirtualHost *:80>
    ServerName issue-tracker.local
    DocumentRoot /var/www/lampstack/public

    <Directory /var/www/lampstack/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/issue-tracker-error.log
    CustomLog ${APACHE_LOG_DIR}/issue-tracker-access.log combined
</VirtualHost>
```

Enable the site and reload Apache:

```bash
sudo a2ensite issue-tracker.conf
sudo systemctl reload apache2
```

If you are using a local VM without DNS, add an entry to `/etc/hosts`:

```bash
echo "127.0.0.1 issue-tracker.local" | sudo tee -a /etc/hosts
```

### Usage
1. Place the entire project folder on the VM (e.g., `/var/www/lampstack`).
2. Ensure the web server user (usually `www-data`) can read the files:
   ```bash
   sudo chown -R www-data:www-data /var/www/lampstack
   sudo find /var/www/lampstack -type d -exec chmod 755 {} \;
   sudo find /var/www/lampstack -type f -exec chmod 644 {} \;
   ```
3. Visit `http://issue-tracker.local` (or the hostname you configured).
4. Create issues, assign owners, and update statuses directly from the single-page interface.
   - Each status change form includes a text box to log progress. Every submission is stored in the update history and shown in both the UI and exported reports.
5. To review all historical updates, open `http://localhost:8080/updates.php` (or the VM hostname). Use the pagination controls to navigate the full audit log.

### Maintenance Tips
- Create regular database backups:
  ```bash
  mysqldump -u lamp_user -p lamp_issue_tracker > backup.sql
  ```
- To deploy updates, copy new files into place and reload Apache if configuration changes were made.
- Monitor logs under `/var/log/apache2/` for errors.

### Troubleshooting
- If PHP errors do not render in the browser, check Apache’s error log:
  ```bash
  tail -f /var/log/apache2/issue-tracker-error.log
  ```
- When a database connection fails, verify credentials in `includes/config.php` and confirm the MySQL user has `SELECT`, `INSERT`, and `UPDATE` privileges.
# lampstack
