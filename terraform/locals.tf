# locals.tf

// Defina valores locais reutilizáveis. Valores locais simplificam a interpolação e mantêm
// expressões repetidas em um só lugar. O `name_prefix` é usado para construir
// nomes de recursos, e `default_tags` aplica tags consistentes a todos os
// recursos por meio do recurso default_tags do provedor em main.tf.

locals {
  # Prefixo para nomear recursos como VPCs, sub-redes e instâncias EC2. Um
  # hífen é adicionado automaticamente. Por exemplo, se o nome do projeto for
  # "demo", a VPC será chamada de "demo-vpc".
  name_prefix = "${var.project_name}"

  # Tags padrão aplicadas a todos os recursos da AWS. Você pode estender ou
  # personalizar este mapa conforme necessário. O bloco provider em main.tf usa
  # essas tags para marcação padrão. Recursos individuais podem fornecer uma
  # tag de nome além dessas tags padrão.
  default_tags = {
    Project    = var.project_name
    ManagedBy  = "Terraform"
    Environment = "dev"
  }
}
