<?php
/*
Plugin Name: WooCommerce Correios
Plugin URI: http://felipematos.com/loja
Description: Adiciona entrega por correios
Version: 1.0
Author: Felipe Matos <chucky_ath@yahoo.com.br>
Author URI: http://felipematos.com
License: GPLv2
Requires at least: 3.0
Tested up to: 3.3.1
*/

//hook to include the payment gateway function
add_action('plugins_loaded', 'shipping_correios', 0);

//hook function
function shipping_correios(){
    class correios extends WC_Shipping_Method {

        function __construct() { 
            $this->id = 'correios';
            $this->method_title = __('Correios Brasil', 'woothemes');
            
            $this->init();
        }
        
        function init(){
            global $woocommerce;
            
            //Load admin form options
            $this->init_form_fields();
        
            // Load the settings.
            $this->init_settings();
            
            // Define user set variables
            $this->enabled = $this->settings['enabled'];
            $this->title = $this->settings['title'];
            $this->postalcode = $this->settings['postalcode']; 
            $this->debug = $this->settings['debug']; 
            $this->enable_pac = $this->settings['enable_pac']; 
            $this->enable_sedex = $this->settings['enable_sedex']; 
            $this->enable_sedex_cobrar = $this->settings['enable_sedex_cobrar']; 
            $this->enable_sedex10 = $this->settings['enable_sedex10']; 
            $this->enable_esedex = $this->settings['enable_esedex']; 
            $this->cod_empresa = $this->settings['cod_empresa']; 
            $this->senha = $this->settings['senha']; 
            
            // Logs
            if ($this->debug=='yes') $this->log = $woocommerce->logger();

            // Add Actions
            add_action('woocommerce_update_options_shipping_'.$this->id, array(&$this, 'process_admin_options'));
        }
        
        /**
        * Initialise Gateway Settings Form Fields
        */
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'  => __( 'Enable', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Enable local pickup', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'woothemes' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
                    'default' => __( 'Correios', 'woothemes' )
                ),
                'postalcode' => array(
                    'title' => __( 'Sender Postal Code', 'woothemes' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
                    'default' => __( '05713520', 'woothemes' )
                ),
                'debug' => array(
                    'title' => __( 'Debug', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Enable debugging <code>/woocommerce/logs/correios.log</code>', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'enable_pac' => array(
                    'title' => __( 'PAC', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Enable PAC shipping method', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'enable_sedex' => array(
                    'title' => __( 'SEDEX', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Enable SEDEX shipping method', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'enable_sedex_cobrar' => array(
                    'title' => __( 'SEDEX a Cobrar', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Enable SEDEX a cobrar shipping method', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'enable_sedex10' => array(
                    'title' => __( 'SEDEX 10', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Enable SEDEX 10 shipping method', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'enable_esedex' => array(
                    'title' => __( 'e-Sedex', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Enable e-Sedex (with contract) shipping method', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'cod_empresa' => array(
                    'title' => __( 'Código Administrativo', 'woothemes' ), 
                    'type' => 'text', 
                    'description' => __( 'The administrative code registered in Correios (necessary for eSedex)', 'woothemes' ), 
                    'default' => ''
                ),
                'senha' => array(
                    'title' => __( 'Senha', 'woothemes' ), 
                    'type' => 'text', 
                    'description' => __( 'Password to access the services for you contract (necessary for eSedex)', 'woothemes' ), 
                    'default' => ''
                )
            );
        }

		//======================================================================
		//Admin Panel Options
		//Options for bits like 'title' and availability on a country-by-country basis
		//======================================================================
		public function admin_options() {
			?>
			<h3><?php echo $this->method_title; ?></h3>
			<p><?php _e('Correios is the Brazil postal office method of shipping, and you can enable several shipping methods for it.', 'woothemes'); ?></p>
			<table class="form-table">
				<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
				?>
			</table><!--/.form-table-->
			<?php
		} // End admin_options()
        
        function is_available( $package ) {
            return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true );
        }
        
        function calculaFrete($cod_servico, $cep_origem, $cep_destino, $peso, $altura='2', $largura='11', $comprimento='16', $valor_declarado='0.50', $codigo_empresa='',$senha=''){
            #OFICINADANET###############################
            # Código dos Serviços dos Correios
            # 41106 PAC sem contrato
            # 40010 SEDEX sem contrato
            # 40045 SEDEX a Cobrar, sem contrato
            # 40215 SEDEX 10, sem contrato
            # 40290 SEDEX HOJE
            ############################################
            global $woocommerce;
            
            $correios = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?nCdEmpresa=".$codigo_empresa."&sDsSenha=".$senha."&sCepOrigem=".$cep_origem."&sCepDestino=".$cep_destino."&nVlPeso=".$peso."&nCdFormato=1&nVlComprimento=".$comprimento."&nVlAltura=".$altura."&nVlLargura=".$largura."&sCdMaoPropria=n&nVlValorDeclarado=".$valor_declarado."&sCdAvisoRecebimento=n&nCdServico=".$cod_servico."&nVlDiametro=0&StrRetorno=xml";
            if ($this->debug=='yes') $this->log->add( 'correios', "URL: ".$correios);
            $xml = simplexml_load_file($correios);
            if($xml->cServico->Erro == '0')
                return $xml->cServico->Valor;
            else
                return false;
            //return 99;
        }
            
        function calculate_shipping(){
            global $woocommerce;
            $customer = $woocommerce->customer;  

            $length = 0;
            $width = 0;
            $height = 0;
            $peso_cubico = 0;

            // Shipping per item
            foreach ($woocommerce->cart->get_cart() as $item_id => $values) {
                $_product = $values['data'];
                if ($values['quantity']>0) { //&& $_product->needs_shipping()) {
                    if($_product->has_dimensions()){
                        if ($this->debug=='yes') $this->log->add( 'correios', "Produto: ".$_product->get_sku());
                        if ($this->debug=='yes') $this->log->add( 'correios', "Dimensões: ".$_product->get_dimensions());
                        $dimensions = explode(' × ',$_product->get_dimensions());
                        $length += $dimensions[0];
                        $width += $dimensions[1];
                        $height += str_replace(' '.get_option('woocommerce_dimension_unit'),'',$dimensions[2]);
                        $peso_cubico = $peso_cubico + (($length * $width * $height)/1000);
                        $valor_total += $_product->get_price();
                    }
                }
            }
            if ($length < 16){
                if ($this->debug=='yes') $this->log->add( 'correios', "Comprimento não pode ser inferior a 16 cm. Ajustando Comprimento...");
                $length = 16;
            }
            
            if ($this->debug=='yes') $this->log->add( 'correios', "Peso cúbico: ".$peso_cubico);
            if ($this->debug=='yes') $this->log->add( 'correios', "Altura: ".$height);
            if ($this->debug=='yes') $this->log->add( 'correios', "Largura: ".$width);
            if ($this->debug=='yes') $this->log->add( 'correios', "Comprimento: ".$length);
            
            // Register the rate
            if($this->enable_pac=='yes') {
                $cost_pac = number_format($this->calculaFrete('41106',$this->postalcode,$woocommerce->customer->get_postcode(),$peso_cubico,$height ,$width,$length,$valor_total),2,'.','');
                if ($this->debug=='yes') $this->log->add( 'correios', "Valor PAC: ".$cost_pac);
                $this->add_rate(array('id'=> 'pac','label'=> 'PAC','cost'=> $cost_pac,'calc_tax'=>'per_order'));
            }
            if($this->enable_sedex=='yes') {
                $cost_sedex = number_format($this->calculaFrete('40010',$this->postalcode,$woocommerce->customer->get_postcode(),$peso_cubico,$height ,$width,$length,$valor_total),2,'.','');
                if ($this->debug=='yes') $this->log->add( 'correios', "Valor SEDEX: ".$cost_sedex);
                $this->add_rate(array('id'=> 'sedex','label'=> 'SEDEX','cost'=> $cost_sedex,'calc_tax'=>'per_order'));
            }
            if($this->enable_sedex_cobrar=='yes') {
                $cost_sedex_cobrar = number_format($this->calculaFrete('40045',$this->postalcode,$woocommerce->customer->get_postcode(),$peso_cubico,$height ,$width,$length,$valor_total),2,'.','');
                if ($this->debug=='yes') $this->log->add( 'correios', "Valor SEDEX a Cobrar: ".$cost_sedex_cobrar);
                $this->add_rate(array('id'=> 'sedexcobrar','label'=> 'SEDEX a cobrar','cost'=> $cost_sedex_cobrar,'calc_tax'=>'per_order'));
            }
            if($this->enable_sedex10=='yes') {
                $cost_sedex10 = number_format($this->calculaFrete('40215',$this->postalcode,$woocommerce->customer->get_postcode(),$peso_cubico,$height ,$width,$length,$valor_total),2,'.','');
                if ($this->debug=='yes') $this->log->add( 'correios', "Valor SEDEX 10: ".$cost_sedex10);
                $this->add_rate(array('id'=> 'sedex10','label'=> 'SEDEX 10','cost'=> $cost_sedex10,'calc_tax'=>'per_order'));
            }
            if($this->enable_esedex=='yes' && $this->cod_empresa!='' && $this->senha!='') {
                $cost_esedex = number_format($this->calculaFrete('81019',$this->postalcode,$woocommerce->customer->get_postcode(),$peso_cubico,$height ,$width,$length,$valor_total),2,'.','');
                if ($this->debug=='yes') $this->log->add( 'correios', "Valor e-Sedex: ".$cost_esedex);
                $this->add_rate(array('id'=> 'esedex','label'=> 'e-Sedex','cost'=> $cost_esedex,'calc_tax'=>'per_order'));
            }
        }
    }
  
    function add_correios_method( $methods ) {
        $methods[] = 'correios'; return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'add_correios_method' );
}