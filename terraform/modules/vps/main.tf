# VPS Module - Hetzner Cloud Server
# Provisions a Ubuntu 24.04 server with PHP 8.3, Caddy, and required dependencies

terraform {
  required_providers {
    hcloud = {
      source  = "hetznercloud/hcloud"
      version = "~> 1.45"
    }
  }
}

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
  description = "Server location (fsn1, nbg1, hel1, ash, hil)"
  type        = string
  default     = "fsn1"  # Falkenstein, Germany
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
      - apt-transport-https
      - ca-certificates
      - gnupg
      - lsb-release

    runcmd:
      # Install Docker
      - install -m 0755 -d /etc/apt/keyrings
      - curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
      - chmod a+r /etc/apt/keyrings/docker.gpg
      - echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
      - apt-get update
      - apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
      - systemctl enable docker
      - systemctl start docker

      # Create application directory
      - mkdir -p /var/www/samwilkinson

      # Create deployment user with docker access
      - useradd -m -s /bin/bash deploy
      - usermod -aG docker deploy
      - mkdir -p /home/deploy/.ssh
      - echo "${var.ssh_public_key}" > /home/deploy/.ssh/authorized_keys
      - chown -R deploy:deploy /home/deploy/.ssh
      - chmod 700 /home/deploy/.ssh
      - chmod 600 /home/deploy/.ssh/authorized_keys
      - chown -R deploy:deploy /var/www/samwilkinson

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
          Architecture: Docker Compose (Caddy + Symfony)

          Application: /var/www/samwilkinson
          Logs: docker compose -f /var/www/samwilkinson/docker-compose.prod.yml logs

          Quick commands:
            - Status: docker compose -f /var/www/samwilkinson/docker-compose.prod.yml ps
            - Logs: docker compose -f /var/www/samwilkinson/docker-compose.prod.yml logs -f
            - Restart: docker compose -f /var/www/samwilkinson/docker-compose.prod.yml restart

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
