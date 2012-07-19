﻿<?php
/*
Plugin Name: WooCommerce Correios
Plugin URI: http://felipematos.com/loja
Description: Adiciona o método de entrega por correios
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
            $this->method_title = __('Correios Brasil', 'woocommerce');
            
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
                    'title' 		=> __( 'Enable', 'woocommerce' ), 
                    'type' 			=> 'checkbox', 
                    'label' 		=> __( 'Enable local pickup', 'woocommerce' ), 
                    'default' 		=> 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Correios', 'woocommerce' )
                ),
                'postalcode' => array(
                    'title' => __( 'Sender Postal Code', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( '05713520', 'woocommerce' )
                ),
                'debug' => array(
                    'title' 		=> __( 'Debug', 'woocommerce' ), 
                    'type' 			=> 'checkbox', 
                    'label' 		=> __( 'Enable debugging <code>/woocommerce/logs/correios.log</code>', 'woocommerce' ), 
                    'default' 		=> 'yes'
                ),
                'enable_pac' => array(
                    'title' 		=> __( 'PAC', 'woocommerce' ), 
                    'type' 			=> 'checkbox', 
                    'label' 		=> __( 'Enable PAC shipping method', 'woocommerce' ), 
                    'default' 		=> 'yes'
                ),
                'enable_sedex' => array(
                    'title' 		=> __( 'SEDEX', 'woocommerce' ), 
                    'type' 			=> 'checkbox', 
                    'label' 		=> __( 'Enable SEDEX shipping method', 'woocommerce' ), 
                    'default' 		=> 'yes'
                ),
                'enable_sedex_cobrar' => array(
                    'title' 		=> __( 'SEDEX a Cobrar', 'woocommerce' ), 
                    'type' 			=> 'checkbox', 
                    'label' 		=> __( 'Enable SEDEX a cobrar shipping method', 'woocommerce' ), 
                    'default' 		=> 'yes'
                ),
                'enable_sedex10' => array(
                    'title' 		=> __( 'SEDEX 10', 'woocommerce' ), 
                    'type' 			=> 'checkbox', 
                    'label' 		=> __( 'Enable SEDEX 10 shipping method', 'woocommerce' ), 
                    'default' 		=> 'yes'
                )
            );
        }
        
        function admin_options() {
            global $woocommerce; ?>
            <h3><?php echo $this->method_title; ?></h3>
            <p><?php _e('Local pickup is a simple method which allows the customer to pick up their order themselves.', 'woocommerce'); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table> <?php
        }
        
        function is_available( $package ) {
            return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true );
        }
        
        function calculaFrete($cod_servico, $cep_origem, $cep_destino, $peso, $altura='2', $largura='11', $comprimento='16', $valor_declarado='0.50'){
            #OFICINADANET###############################
            # Código dos Serviços dos Correios
            # 41106 PAC sem contrato
            # 40010 SEDEX sem contrato
            # 40045 SEDEX a Cobrar, sem contrato
            # 40215 SEDEX 10, sem contrato
            # 40290 SEDEX HOJE
            ############################################
            global $woocommerce;
            
            $correios = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?nCdEmpresa=&sDsSenha=&sCepOrigem=".$cep_origem."&sCepDestino=".$cep_destino."&nVlPeso=".$peso."&nCdFormato=1&nVlComprimento=".$comprimento."&nVlAltura=".$altura."&nVlLargura=".$largura."&sCdMaoPropria=n&nVlValorDeclarado=".$valor_declarado."&sCdAvisoRecebimento=n&nCdServico=".$cod_servico."&nVlDiametro=0&StrRetorno=xml";
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
        }
    }
  
    function add_correios_method( $methods ) {
        $methods[] = 'correios'; return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'add_correios_method' );
}
?>