<?php
/*
Plugin Name: WooCommerce Correios
Plugin URI: http://felipematos.com/loja
Description: Adiciona entrega por correios
Version: 1.2
Author: Felipe Matos <chucky_ath@yahoo.com.br>
Author URI: http://felipematos.com
Requires at least: 3.0
Tested up to: 3.3.4
*/

//hook to include the payment gateway function
add_action('plugins_loaded', 'shipping_correios', 0);

//hook function
function shipping_correios(){

	require_once('correios.php');

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
            $this->enable_sedex_hoje = $this->settings['enable_sedex_hoje']; 
            $this->enable_esedex = $this->settings['enable_esedex']; 
            $this->valor_declarado = $this->settings['valor_declarado']; 
            $this->mao_propria = $this->settings['mao_propria']; 
            $this->aviso_recebimento = $this->settings['aviso_recebimento']; 
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
                    'title'  => __( 'Habilita/Desabilita', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Habilita envio por Correios', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Título', 'woothemes' ),
                    'type' => 'text',
                    'description' => __( 'Título a ser exibido durante o checkout.', 'woothemes' ),
                    'default' => __( 'Correios', 'woothemes' )
                ),
                'postalcode' => array(
                    'title' => __( 'CEP de Origem', 'woothemes' ),
                    'type' => 'text',
                    'description' => __( 'CEP onde se encontra o produto para calcular o frete.', 'woothemes' ),
                    'default' => __( '00000000', 'woothemes' )
                ),
                'debug' => array(
                    'title' => __( 'Debug', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Habilita escrita de log <code>/woocommerce/logs/correios.log</code>.', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'enable_pac' => array(
                    'title' => __( 'PAC', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Habilita envio por PAC.', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'enable_sedex' => array(
                    'title' => __( 'SEDEX', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Habilita envio por SEDEX.', 'woothemes' ), 
                    'default' => 'yes'
                ),
                'enable_sedex_cobrar' => array(
                    'title' => __( 'SEDEX a Cobrar', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Habilita envio por SEDEX à cobrar (necessita declaração de valor).', 'woothemes' ), 
                    'default' => 'no'
                ),
                'enable_sedex10' => array(
                    'title' => __( 'SEDEX 10', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Habilita envio por SEDEX 10.', 'woothemes' ), 
                    'default' => 'no'
                ),
                'enable_sedex_hoje' => array(
                    'title' => __( 'SEDEX Hoje', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Habilita envio por SEDEX Hoje.', 'woothemes' ), 
                    'default' => 'no'
                ),
                'enable_esedex' => array(
                    'title' => __( 'e-Sedex', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Habilita envio por e-SEDEX (necessita contrato com correios).', 'woothemes' ), 
                    'default' => 'no'
                ),
                'valor_declarado' => array(
                    'title' => __( 'Declarar Valor?', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Utiliza a opção de valor declarado no pacote para os correios. Necessário para Sedex à cobrar.', 'woothemes' ), 
                    'default' => 'no'
                ),
                'mao_propria' => array(
                    'title' => __( 'Mão própria?', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Utiliza a opção de mão própria no pacote para os correios.', 'woothemes' ), 
                    'default' => 'no'
                ),
                'aviso_recebimento' => array(
                    'title' => __( 'Aviso de Recebimento?', 'woothemes' ), 
                    'type' => 'checkbox', 
                    'label' => __( 'Utiliza a opção de aviso de recebimento no pacote para os correios.', 'woothemes' ), 
                    'default' => 'no'
                ),
                'cod_empresa' => array(
                    'title' => __( 'Código Administrativo', 'woothemes' ), 
                    'type' => 'text', 
                    'description' => __( 'Código informado pelos correios ao firmar o contrato (necessário para e-SEDEX).', 'woothemes' ), 
                    'default' => ''
                ),
                'senha' => array(
                    'title' => __( 'Senha', 'woothemes' ), 
                    'type' => 'text', 
                    'description' => __( 'Senha de acesso do seu contrato nos correios (necessário para e-SEDEX).', 'woothemes' ), 
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
        
		
        function calculate_shipping(){
            global $woocommerce;
            $customer = $woocommerce->customer;  

            $length = 0;
            $width = 0;
            $height = 0;
			$weight = 0;
            $valor_total = 0;

            // Shipping per item
            foreach ($woocommerce->cart->get_cart() as $item_id => $values) {
                $_product = $values['data'];
                if ($values['quantity']>0) { //&& $_product->needs_shipping()) {
                    if($_product->has_dimensions()){
                        //if ($this->debug=='yes') $this->log->add( 'correios', "Produto: ".$_product->get_sku());
                        //if ($this->debug=='yes') $this->log->add( 'correios', "Dimensões: ".$_product->get_dimensions());
                        //if ($this->debug=='yes') $this->log->add( 'correios', "Peso: ".$_product->get_weight());
                        $dimensions = explode(' × ',$_product->get_dimensions());
                        $length += $dimensions[0];
                        $width += $dimensions[1];
                        $height += str_replace(' '.get_option('woocommerce_dimension_unit'),'',$dimensions[2]);
						$weight += $_product->get_weight();
                        $valor_total += $_product->get_price();
                    }
                }
            }
			
			$maopropria=($this->mao_propria=='yes'?true:false);
			$valordeclarado=($this->valor_declarado=='yes'?true:false);
			$avisorecebimento=($this->aviso_recebimento=='yes'?true:false);
			
            if ($this->debug=='yes') $this->log->add( 'correios', "dimensões: ".$length.'x'.$height.'x'.$width.' - peso: '.$weight.' - valor: '.$valor_total.' - CEP: '.$woocommerce->customer->get_postcode());
            if ($this->debug=='yes') $this->log->add( 'correios', "mão própria: ".$maopropria." - valor declarado: ".$valordeclarado." - aviso recebimento: ".$avisorecebimento);

			$correios = new frete_correios($this->postalcode, $maopropria, $valordeclarado, $avisorecebimento, $this->cod_empresa, $this->senha);
			$frete = $correios->calculaFrete($woocommerce->customer->get_postcode(), $weight, $length, $height, $width, $valor_total);
			
			foreach($frete as $key => $value){
				switch($key){
					case 'pac':
						if($this->enable_pac=='yes' && $value['valor'] > 0 && $value['erro'] == '(0)'){
							$this->add_rate(array('id'=> 'pac','label'=> 'PAC','cost'=> $value['valor'],'calc_tax'=>'per_order'));
						}
						break;
					case 'sedex':
						if($this->enable_sedex=='yes' && $value['valor'] > 0 && $value['erro'] == '(0)'){
							$this->add_rate(array('id'=> 'sedex','label'=> 'SEDEX','cost'=> $value['valor'],'calc_tax'=>'per_order'));
						}
						break;
					case 'sedex_10':
						if($this->enable_sedex10=='yes' && $value['valor'] > 0 && $value['erro'] == '(0)'){
							$this->add_rate(array('id'=> 'sedex10','label'=> 'SEDEX10','cost'=> $value['valor'],'calc_tax'=>'per_order'));
						}
						break;
					case 'sedex_hoje':
						if($this->enable_sedex_hoje=='yes' && $value['valor'] > 0 && $value['erro'] == '(0)'){
							$this->add_rate(array('id'=> 'sedexhoje','label'=> 'SEDEX Hoje','cost'=> $value['valor'],'calc_tax'=>'per_order'));
						}
						break;
					case 'sedex_cobrar':
						if($this->enable_sedex_cobrar=='yes' && $value['valor'] > 0 && $value['erro'] == '(0)'){
							$this->add_rate(array('id'=> 'sedexcobrar','label'=> 'SEDEX à Cobrar','cost'=> $value['valor'],'calc_tax'=>'per_order'));
						}
						break;
					case 'esedex':
						if($this->enable_esedex=='yes' && $value['valor'] > 0 && $value['erro'] == '(0)'){
							$this->add_rate(array('id'=> 'esedex','label'=> 'e-SEDEX','cost'=> $value['valor'],'calc_tax'=>'per_order'));
						}
						break;
				}
				
				if ($this->debug=='yes') $this->log->add( 'correios', $key .' = R$ '. $value['valor'] .' | '. $value['erro']);
			}
        }
    }
  
    function add_correios_method( $methods ) {
        $methods[] = 'correios'; return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'add_correios_method' );
}
?>