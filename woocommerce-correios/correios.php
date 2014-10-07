<?php
	class frete_correios {
		
		//references:
		//http://blog.correios.com.br/comercioeletronico/?p=150
		//http://www.correios.com.br/webServices/PDF/SCPP_manual_implementacao_calculo_remoto_de_precos_e_prazos.pdf
		//http://www.correios.com.br/produtosaz/produto.cfm?id=8560360B-5056-9163-895DA62922306ECA
		
		
		var $postalcode;
		var $mao_propria;
		var $valor_declarado;
		var $aviso_recebimento;
		var $cod_empresa;
		var $senha;
		var $formato = 1 ; //1: Formato caixa/pacote - 2: Formato rolo/prisma - 3: Envelope
		var $cod_servico = array(
				 40010 => 'sedex'
				,40045 => 'sedex_cobrar'
				,40215 => 'sedex_10'
				,40290 => 'sedex_hoje'
				,41106 => 'pac'
				,81019 => 'esedex');	
		
		function setCEP($codigo){
			$this->postalcode = $codigo;
		}
		function setMaoPropria($enabled){
			$this->mao_propria = ($enabled ? 's' : 'n');
		}
		function setValorDeclarado($enabled){
			$this->valor_declarado = ($enabled ? 's' : 'n');
		}
		function setAvisoRecebimento($enabled){
			$this->aviso_recebimento = ($enabled ? 's' : 'n');
		}
		function setContrato($codigo, $senha){
			$this->cod_empresa = $codigo;
			$this->senha = $senha;
		}
		
		function __construct($cep, $maopropria = false, $valordeclarado = false, $avisorecebimento = false, $codempresa = '', $senha = ''){
			$this->setCEP($cep);
			$this->setMaoPropria($maopropria);
			$this->setValorDeclarado($valordeclarado);
			$this->setAvisoRecebimento($avisorecebimento);
			$this->setContrato($codempresa, $senha);
		}
		
        function calculaFrete($cep_destino, $peso, $comprimento, $altura, $largura, $valor_declarado, $diametro = 0){
			
			//-16 : A largura não pode ser maior que 105 cm.
			//-20 : A largura não pode ser inferior a 11 cm.
			if($largura < 11) $largura = 11;
			if($largura > 105) $largura = 105;
			
			//-15 : O comprimento não pode ser maior que 105 cm.
			//-22 : O comprimento não pode ser inferior a 16 cm.
			if($comprimento < 16) $comprimento = 16;
			if($comprimento > 105) $comprimento = 105;
			
			//-17 : A altura não pode ser maior que 105 cm.
			//-18 : A altura não pode ser inferior a 2 cm.
			if($altura < 2) $altura = 2;
			if($altura > 105) $altura = 105;
			
			//-23 : A soma resultante do comprimento + largura + altura não deve superar a 200 cm.
			while($soma > 200){
				$soma = $comprimento + $largura + $altura;
				if ($soma > 200){
					$comprimento--;
					$largura--;
					$altura--;
				}
			}
			
			//-5 : O Valor Declarado não deve exceder R$ 10.000,00
			if($valor_declarado > 10000.00) $valor_declarado = 10000.00;
			
			//-4 : Peso excedido.
			//Cálculo do peso cúbico: http://blog.correios.com.br/comercioeletronico/?p=150
			$peso_cubico = ($comprimento * $altura * largura)/6000;
			if($peso_cubico > 5 && $peso_cubico > $peso) $peso = $peso_cubico;
			
			
			$args['nCdEmpresa'] = $this->cod_empresa;
			$args['sDsSenha'] = $this->senha;
			$args['sCepOrigem'] = $this->postalcode;
			$args['sCepDestino'] = $cep_destino;
			$args['nVlPeso'] = $peso;
			$args['nCdFormato'] = $this->formato;
			$args['nVlComprimento'] = $comprimento;
			$args['nVlAltura'] = $altura;
			$args['nVlLargura'] = $largura;
			$args['sCdMaoPropria'] = $this->mao_propria;
			$args['nVlValorDeclarado'] = ($this->valor_declarado == 's' ? $valor_declarado : 0);
			$args['sCdAvisoRecebimento'] = $this->aviso_recebimento;
			$args['nCdServico'] = '40010,40045,40215,40290,41106,81019';
			$args['nVlDiametro'] = $diametro;
			$args['StrRetorno'] = 'xml';
			
			$url = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?". http_build_query($args);
			
            $xml = simplexml_load_file($url);
			$result = array();
			foreach($xml as $servico){
				$cod = (int)$servico->Codigo;
				$name = (string)$this->cod_servico[$cod];
				$valor = (string)$servico->Valor;
				$result[$name]['valor'] = (float)number_format(str_replace(',','.',$valor),2,'.','');
				$result[$name]['codigo'] = $cod;
				$result[$name]['erro'] = (string)('('. $servico->Erro . ')'. $servico->MsgErro);
			}
			return $result;
        }
	}
?>