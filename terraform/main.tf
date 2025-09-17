# main.tf

// Configuração do Terraform para a rede single-AZ e a pilha de computação. Este
// arquivo contém os blocos de nível superior `terraform` e `provider`, bem como um
// exemplo de uso. Consulte os outros arquivos *.tf para obter as definições de
// recursos individuais, como VPC, sub-redes, tabelas de rotas, grupos de segurança, instâncias EC2 e o banco de dados RDS. Todos os recursos são marcados
// consistentemente usando um conjunto de tags padrão definidas em locals.tf.
terraform {
  required_version = ">= 1.6"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = ">= 5.0"
    }
  }
}

provider "aws" {
  region      = var.region

  # Aplique tags padrão a todos os recursos da AWS. Essas tags vêm de
  # `locals.default_tags`, que é definido em locals.tf e usa a
  # variável project_name. A marcação de recursos ajuda na alocação de custos,
  # descoberta e gerenciamento. A tag Name é definida individualmente em cada
  # recurso, quando apropriado.
  default_tags {
    tags = local.default_tags
  }
}

/*
Uso
-----

1. Inicialize o diretório de trabalho do Terraform. Isso baixará os
   provedores necessários (AWS) e configurará o backend. Execute este comando
   no diretório `terraform`:

   terraform init

2. Revise o plano de execução. Substitua `MEU_IP/32` pelo seu
   endereço IPv4 público real em notação CIDR (por exemplo, `203.0.113.10/32`).
   Forneça sua chave pública SSH na variável `ssh_public_key`. Quaisquer
   variáveis ​​não especificadas assumirão seus valores padrão definidos em
   variables.tf.

   terraform plan \
     -var="allowed_ssh_cidr=MEU_IP/32" \
     -var="ssh_public_key=ssh‑rsa AAAAB3Nza..."

3. Aplique o plano para criar a infraestrutura. Use as mesmas substituições de variáveis
   do comando `plan`. O Terraform solicitará confirmação antes de provisionar os recursos:

   terraform apply \
     -var="allowed_ssh_cidr=MEU_IP/32" \
     -var="ssh_public_key=ssh‑rsa AAAAB3Nza..."

4. Após a conclusão da aplicação, o Terraform exibirá informações úteis,
   incluindo o IP público do bastion, o IP privado da instância do aplicativo
   e o endpoint RDS. Use o host bastion para acessar a
   instância privada do aplicativo e o banco de dados.
*/
