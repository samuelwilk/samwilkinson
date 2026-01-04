# VPS Module - Hetzner Cloud Server
# Provisions a Ubuntu 24.04 server with PHP 8.3, Caddy, and required dependencies

variable "server_name" {
  description = "Name of the server"
  type        = string
}

variable "server_type" {
  description = "Hetzner server type (cpx11, cpx21, etc.)"
  type        = string
  default     = "cpx11"  # 2 vCPU, 2GB RAM
}

variable "location" {
  description = "Server location (nbg1, fsn1, hel1, ash, hil)"
  type        = string
  default     = "nbg1"  # Nuremberg
}

variable "ssh_public_key" {
  description = "SSH public key for root access"
  type        = string
}

variable "environment" {
  description = "Environment name (production, staging)"
  type        = string
}

# SSH Key
resource "hcloud_ssh_key" "default" {
  name       = "${var.server_name}-key"
  public_key = var.ssh_public_key
}

# Firewall
resource "hcloud_firewall" "web" {
  name = "${var.server_name}-firewall"

  # SSH
  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "22"
    source_ips = ["0.0.0.0/0", "::/0"]
  }

  # HTTP
  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "80"
    source_ips = ["0.0.0.0/0", "::/0"]
  }

  # HTTPS
  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "443"
    source_ips = ["0.0.0.0/0", "::/0"]
  }

  # ICMP (ping)
  rule {
    direction  = "in"
    protocol   = "icmp"
    source_ips = ["0.0.0.0/0", "::/0"]
  }
}

# Server
resource "hcloud_server" "web" {
  name        = var.server_name
  server_type = var.server_type
  image       = "ubuntu-24.04"
  location    = var.location
  ssh_keys    = [hcloud_ssh_key.default.id]
  firewall_ids = [hcloud_firewall.web.id]

  labels = {
    environment = var.environment
    managed_by  = "terraform"
    application = "samwilkinson"
  }

  # Cloud-init script for initial setup
  user_data = <<-EOT
    #cloud-config
    package_update: true
    package_upgrade: true

    packages:
      - git
      - curl
      - wget
      - unzip
      - software-properties-common
      - apt-transport-https
      - ca-certificates
      - gnupg
      - lsb-release

    runcmd:
      # Add PHP 8.3 repository
      - add-apt-repository ppa:ondrej/php -y
      - apt-get update

      # Install PHP 8.3 and extensions
      - apt-get install -y php8.3 php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-sqlite3 php8.3-gd php8.3-intl php8.3-opcache php8.3-curl php8.3-zip

      # Install Composer
      - curl -sS https://getcomposer.org/installer | php
      - mv composer.phar /usr/local/bin/composer
      - chmod +x /usr/local/bin/composer

      # Install Caddy
      - apt install -y debian-keyring debian-archive-keyring
      - curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
      - curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
      - apt-get update
      - apt-get install -y caddy

      # Create application directory
      - mkdir -p /var/www/samwilkinson
      - chown -R www-data:www-data /var/www

      # Configure PHP-FPM
      - sed -i 's/pm.max_children = 5/pm.max_children = 10/' /etc/php/8.3/fpm/pool.d/www.conf
      - sed -i 's/;pm.max_requests = 500/pm.max_requests = 500/' /etc/php/8.3/fpm/pool.d/www.conf

      # Enable OPcache
      - echo "opcache.enable=1" >> /etc/php/8.3/fpm/conf.d/10-opcache.ini
      - echo "opcache.memory_consumption=128" >> /etc/php/8.3/fpm/conf.d/10-opcache.ini
      - echo "opcache.interned_strings_buffer=8" >> /etc/php/8.3/fpm/conf.d/10-opcache.ini
      - echo "opcache.max_accelerated_files=10000" >> /etc/php/8.3/fpm/conf.d/10-opcache.ini

      # Restart services
      - systemctl restart php8.3-fpm
      - systemctl enable php8.3-fpm caddy

      # Create deployment user
      - useradd -m -s /bin/bash deploy
      - mkdir -p /home/deploy/.ssh
      - echo "${var.ssh_public_key}" > /home/deploy/.ssh/authorized_keys
      - chown -R deploy:deploy /home/deploy/.ssh
      - chmod 700 /home/deploy/.ssh
      - chmod 600 /home/deploy/.ssh/authorized_keys
      - usermod -aG www-data deploy

      # Install fail2ban for SSH protection
      - apt-get install -y fail2ban
      - systemctl enable fail2ban

    write_files:
      - path: /etc/motd
        content: |

          ╔══════════════════════════════════════════╗
          ║    samwilkinson.ca Production Server    ║
          ╚══════════════════════════════════════════╝

          Environment: ${var.environment}
          Managed by: Terraform

          Application: /var/www/samwilkinson
          Logs: /var/log/caddy/, /var/log/php8.3-fpm.log

          Quick commands:
            - Deploy: cd /var/www/samwilkinson && ./deploy.sh
            - Logs: tail -f /var/log/caddy/access.log
            - Restart: systemctl restart php8.3-fpm caddy

  EOT

  # Prevent replacement on user_data changes (cloud-init runs once)
  lifecycle {
    ignore_changes = [user_data]
  }
}

# Outputs
output "server_id" {
  description = "Hetzner server ID"
  value       = hcloud_server.web.id
}

output "server_ip" {
  description = "Public IP address"
  value       = hcloud_server.web.ipv4_address
}

output "server_ipv6" {
  description = "IPv6 address"
  value       = hcloud_server.web.ipv6_address
}

output "server_name" {
  description = "Server name"
  value       = hcloud_server.web.name
}
