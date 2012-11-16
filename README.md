Integração Woocommerce - InvoiceXpress

Quando é colocada uma order na loja o plugin vê se o cliente já existe no InvoiceXpress. Se não existir é criado. De seguida cria uma factura para a order e enviar por e-mail a mesma.

Quando o cliente é criado o plugin regista na base de dados um user_meta 'wc_ie_client_id' com o id. Se este meta estiver definido ele vai tentar buscar este cliente no InvoiceXpress.