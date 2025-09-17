# variables.tf

// Este arquivo declara variáveis ​​de entrada que personalizam a infraestrutura. Muitas
// variáveis ​​têm padrões sensatos para que você possa executar `terraform apply`
// sem especificar nada. Valores sensíveis, como credenciais
// do banco de dados, são marcados como sensíveis para evitar registro acidental.

variable "project_name" {
  description = "Prefixo de nome usado para nomear e marcar recursos. Ele será adicionado a nomes de recursos como VPCs, sub-redes e instâncias."
  type        = string
  default     = "myproject"
}

variable "region" {
  description = "Região da AWS na qual implantar todos os recursos, por exemplo us‑east‑1."
  type        = string
  default     = "us-east-1"
}

variable "az" {
  description = "A Zona de Disponibilidade usada para as sub-redes pública e privada. Deve pertencer à região selecionada."
  type        = string
  default     = "us-east-1a"
}

variable "vpc_cidr" {
  description = "Bloco CIDR para a VPC."
  type        = string
  default     = "10.0.0.0/16"
}

variable "public_subnet_cidr" {
  description = "Bloco CIDR para a sub-rede pública. Esta sub-rede hospeda o bastião e o gateway NAT."
  type        = string
  default     = "10.0.1.0/24"
}

variable "private_subnet_cidr" {
  description = "Bloco CIDR para a sub-rede privada. Esta sub-rede hospeda a instância do aplicativo EC2 e o banco de dados RDS."
  type        = string
  default     = "10.0.2.0/24"
}

variable "private_subnet_cidr_b" {
  description = "CIDR da segunda subrede privada"
  type        = string
  default     = "10.0.3.0/24"
}

variable "az_b" {
  description = "Segunda zona de disponibilidade"
  type        = string
  default     = "us-east-1b"
}

variable "allowed_ssh_cidr" {
  description = "Bloco CIDR com permissão para SSH no host bastion. Deve ser definido como seu endereço IP público seguido de /32."
  type        = string
  default     = "0.0.0.0/0"
}

variable "ssh_public_key" {
  description = "Material da chave pública SSH para associar às instâncias EC2. Use o conteúdo do seu arquivo ~/.ssh/id_rsa.pub (ou similar)."
  type        = string
  default     = ""
}

variable "instance_type_bastion" {
  description = "Tipo de instância EC2 para o host bastion. O padrão é t3.micro."
  type        = string
  default     = "t3.micro"
}

variable "instance_type_app" {
  description = "Tipo de instância EC2 para o host do aplicativo na sub-rede privada. O padrão é t3.micro."
  type        = string
  default     = "t3.micro"
}

variable "db_engine" {
  description = "Mecanismo de banco de dados para a instância do RDS. Por padrão, apenas o Postgres é suportado."
  type        = string
  default     = "postgres"
}

variable "db_engine_version" {
  description = "Versão do mecanismo de banco de dados. Exemplo: 16."
  type        = string
  default     = "16"
}

variable "db_instance_class" {
  description = "Classe de instância do RDS. Exemplo: db.t3.micro."
  type        = string
  default     = "db.t3.micro"
}

variable "db_name" {
  description = "Nome do banco de dados padrão a ser criado."
  type        = string
  default     = "mydb"
}

variable "db_username" {
  description = "Nome de usuário mestre para o banco de dados RDS."
  type        = string
  default     = "postgres"
  sensitive   = true
}

variable "db_password" {
  description = "Senha mestra para o banco de dados RDS. Mantenha esta senha segura."
  type        = string
  default     = "postgrespassword"
  sensitive   = true
}

variable "db_allocated_storage" {
  description = "Quantidade de armazenamento (em GB) a ser alocada para a instância do RDS."
  type        = number
  default     = 20
}
