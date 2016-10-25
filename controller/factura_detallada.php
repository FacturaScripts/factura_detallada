<?php

/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014      Valentín González    valengon@hotmail.com
 * Copyright (C) 2014-2016 Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2015-2016 César Sáez Rodríguez  NATHOO@lacalidad.es
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'plugins/factura_detallada/fpdf17/fs_fpdf.php';
define('FPDF_FONTPATH', 'plugins/factura_detallada/fpdf17/font/');

require_model('cliente.php');
require_model('factura_cliente.php');
require_model('articulo.php');
require_model('divisa.php');
require_model('pais.php');
require_model('forma_pago.php');
require_model('cuenta_banco.php');
require_model('cuenta_banco_cliente.php');
require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';

class factura_detallada extends fs_controller {

   public $cliente;
   public $factura;
   public $impresion;

   public function __construct() {
      parent::__construct(__CLASS__, 'Factura Detallada', 'ventas', FALSE, FALSE);
   }

   protected function private_core() {
      $this->share_extensions();
      
      /// obtenemos los datos de configuración de impresión
      $this->impresion = array(
          'print_ref' => '1',
          'print_dto' => '1',
          'print_alb' => '0',
          'print_formapago' => '1'
      );
      $fsvar = new fs_var();
      $this->impresion = $fsvar->array_get($this->impresion, FALSE);

      $this->factura = FALSE;
      if (isset($_GET['id'])) {
         $factura = new factura_cliente();
         $this->factura = $factura->get($_GET['id']);
      }

      if ($this->factura) {
         $cliente = new cliente();
         $this->cliente = $cliente->get($this->factura->codcliente);

         if (isset($_POST['email'])) {
            $this->enviar_email('factura', $_REQUEST['tipo']);
         } else {
            $filename = 'factura_' . $this->factura->codigo . '.pdf';
            $this->generar_pdf(FALSE, $filename);
         }
      } else {
         $this->new_error_msg("¡Factura de cliente no encontrada!");
      }
   }

   // Corregir el Bug de fpdf con el Simbolo del Euro ---> €
   public function ckeckEuro($cadena) {
      $mostrar = $this->show_precio($cadena, $this->factura->coddivisa);
      $pos = strpos($mostrar, '€');
      if ($pos !== false) {
         if (FS_POS_DIVISA == 'right') {
            return number_format($cadena, FS_NF0, FS_NF1, FS_NF2) . ' ' . EEURO;
         } else {
            return EEURO . ' ' . number_format($cadena, FS_NF0, FS_NF1, FS_NF2);
         }
      }
      return $mostrar;
   }

   public function generar_pdf($archivomail = FALSE, $archivodownload = FALSE) {
      ///// INICIO - Factura Detallada
      /// Creamos el PDF y escribimos sus metadatos
      $pdf_doc = new PDF_MC_Table('P', 'mm', 'A4');
      define('EEURO', chr(128));
      $lineas = $this->factura->get_lineas();
      if($this->impresion['print_dto'])
      {
         $this->impresion['print_dto'] = FALSE;
         
         /// leemos las líneas para ver si de verdad mostramos los descuentos
         foreach($lineas as $lin)
         {
            if($lin->dtopor != 0)
            {
               $this->impresion['print_dto'] = TRUE;
               break;
            }
         }
      }

      $pdf_doc->SetTitle('Factura: ' . $this->factura->codigo . " " . $this->factura->numero2);
      $pdf_doc->SetSubject('Factura del cliente: ' . $this->factura->nombrecliente);
      $pdf_doc->SetAuthor($this->empresa->nombre);
      $pdf_doc->SetCreator('FacturaSctipts V_' . $this->version());

      $pdf_doc->Open();
      $pdf_doc->AliasNbPages();
      $pdf_doc->SetAutoPageBreak(true, 40);

      // Definimos el color de relleno (gris, rojo, verde, azul)
      /// cargamos la configuración
      $fsvar = new fs_var();
      $color = $fsvar->simple_get("f_detallada_color");
      if ($color)
      	$pdf_doc->SetColorRelleno($color);
      else
      	$pdf_doc->SetColorRelleno('azul');

      /// Definimos todos los datos de la cabecera de la factura
      /// Datos de la empresa
      $pdf_doc->fde_nombre = $this->empresa->nombre;
      $pdf_doc->fde_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fde_cifnif = $this->empresa->cifnif;
      $pdf_doc->fde_direccion = $this->empresa->direccion;
      $pdf_doc->fde_codpostal = $this->empresa->codpostal;
      $pdf_doc->fde_ciudad = $this->empresa->ciudad;
      $pdf_doc->fde_provincia = $this->empresa->provincia;
      $pdf_doc->fde_telefono = 'Teléfono: ' . $this->empresa->telefono;
      $pdf_doc->fde_fax = 'Fax: ' . $this->empresa->fax;
      $pdf_doc->fde_email = $this->empresa->email;
      $pdf_doc->fde_web = $this->empresa->web;
      $pdf_doc->fde_piefactura = $this->empresa->pie_factura;

      /// Insertamos el Logo y Marca de Agua
      if( file_exists(FS_MYDOCS.'images/logo.png') OR file_exists(FS_MYDOCS.'images/logo.jpg') )
      {
         $pdf_doc->fdf_verlogotipo = '1'; // 1/0 --> Mostrar Logotipo
         $pdf_doc->fdf_Xlogotipo = '15'; // Valor X para Logotipo
         $pdf_doc->fdf_Ylogotipo = '35'; // Valor Y para Logotipo
         $pdf_doc->fdf_vermarcaagua = '1'; // 1/0 --> Mostrar Marca de Agua
         $pdf_doc->fdf_Xmarcaagua = '25'; // Valor X para Marca de Agua
         $pdf_doc->fdf_Ymarcaagua = '110'; // Valor Y para Marca de Agua
      }
      else
      {
         $pdf_doc->fdf_verlogotipo = '0';
         $pdf_doc->fdf_Xlogotipo = '0';
         $pdf_doc->fdf_Ylogotipo = '0';
         $pdf_doc->fdf_vermarcaagua = '0';
         $pdf_doc->fdf_Xmarcaagua = '0';
         $pdf_doc->fdf_Ymarcaagua = '0';
      }

      // Tipo de Documento
      $pdf_doc->fdf_tipodocumento = 'FACTURA'; // (FACTURA, FACTURA PROFORMA, ¿ALBARAN, PRESUPUESTO?...)
      $pdf_doc->fdf_codigo = $this->factura->codigo . " " . $this->factura->numero2;

      // Fecha, Codigo Cliente y observaciones de la factura
      $pdf_doc->fdf_fecha = $this->factura->fecha;
      $pdf_doc->fdf_codcliente = $this->factura->codcliente;
      $pdf_doc->fdf_observaciones = iconv("UTF-8", "CP1252", $this->fix_html($this->factura->observaciones));

      // Datos del Cliente
      $pdf_doc->fdf_nombrecliente = $this->fix_html($this->factura->nombrecliente);
      $pdf_doc->fdf_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fdf_cifnif = $this->factura->cifnif;
      $pdf_doc->fdf_direccion = $this->fix_html($this->factura->direccion);
      $pdf_doc->fdf_codpostal = $this->factura->codpostal;
      $pdf_doc->fdf_ciudad = $this->factura->ciudad;
      $pdf_doc->fdf_provincia = $this->factura->provincia;
      $pdf_doc->fdc_telefono1 = $this->cliente->telefono1;
      $pdf_doc->fdc_telefono2 = $this->cliente->telefono2;
      $pdf_doc->fdc_fax = $this->cliente->fax;
      $pdf_doc->fdc_email = $this->cliente->email;

      $pdf_doc->fdf_epago = $pdf_doc->fdf_divisa = $pdf_doc->fdf_pais = '';

      // Forma de Pago de la Factura  
      $formapago = $this->_genera_formapago();
      $pdf_doc->fdf_epago = $formapago;
      
      // Divisa de la Factura
      $divisa = new divisa();
      $edivisa = $divisa->get($this->factura->coddivisa);
      if ($edivisa) {
         $pdf_doc->fdf_divisa = $edivisa->descripcion;
      }

      // Pais de la Factura
      $pais = new pais();
      $epais = $pais->get($this->factura->codpais);
      if ($epais) {
         $pdf_doc->fdf_pais = $epais->nombre;
      }
      
      // Cabecera Titulos Columnas
      if($this->impresion['print_dto'])
      {
        $pdf_doc->Setdatoscab(array('ALB', 'DESCRIPCION', 'CANT', 'PRECIO', 'DTO', FS_IVA, 'IMPORTE'));
        $pdf_doc->SetWidths(array(16, 102, 10, 20, 10, 10, 22));
        $pdf_doc->SetAligns(array('C', 'L', 'R', 'R', 'R', 'R', 'R'));
        $pdf_doc->SetColors(array('6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109'));
      }
      else
      {
        $pdf_doc->Setdatoscab(array('ALB', 'DESCRIPCION', 'CANT', 'PRECIO', FS_IVA, 'IMPORTE'));
        $pdf_doc->SetWidths(array(16, 107, 10, 20, 15, 22));
        $pdf_doc->SetAligns(array('C', 'L', 'R', 'R','R', 'R'));
        $pdf_doc->SetColors(array('6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109'));
      }

      /// Definimos todos los datos del PIE de la factura
      /// Lineas de IVA
      $lineas_iva = $this->factura->get_lineas_iva();
      if (count($lineas_iva) > 3) {
         $pdf_doc->fdf_lineasiva = $lineas_iva;
      } else {
         $filaiva = array();
         $i = 0;
         foreach ($lineas_iva as $li) {
            $i++;
            $filaiva[$i][0] = ($li->iva) ? FS_IVA . $li->iva : '';
            $filaiva[$i][1] = ($li->neto) ? $this->ckeckEuro($li->neto) : '';
            $filaiva[$i][2] = ($li->iva) ? $li->iva . "%" : '';
            $filaiva[$i][3] = ($li->totaliva) ? $this->ckeckEuro($li->totaliva) : '';
            $filaiva[$i][4] = ($li->recargo) ? $li->recargo . "%" : '';
            $filaiva[$i][5] = ($li->totalrecargo) ? $this->ckeckEuro($li->totalrecargo) : '';
            $filaiva[$i][6] = ''; //// POR CREARRRRRR
            $filaiva[$i][7] = ''; //// POR CREARRRRRR
            $filaiva[$i][8] = ($li->totallinea) ? $this->ckeckEuro($li->totallinea) : '';
         }

         if ($filaiva) {
            $filaiva[1][6] = $this->factura->irpf . ' %';
            $filaiva[1][7] = $this->ckeckEuro(0 - $this->factura->totalirpf);
         }

         $pdf_doc->fdf_lineasiva = $filaiva;
      }

      // Total factura numerico
      $pdf_doc->fdf_numtotal = $this->ckeckEuro($this->factura->total);

      // Total factura numeros a texto
      $pdf_doc->fdf_textotal = $this->factura->total;

      /// Agregamos la pagina inicial de la factura
      $pdf_doc->AddPage();

      // Lineas de la Factura
      //$lineas = $this->factura->get_lineas();

      if ($lineas) {
         $neto = 0;
         for ($i = 0; $i < count($lineas); $i++) {
            $neto += $lineas[$i]->pvptotal;
            $pdf_doc->neto = $this->ckeckEuro($neto);

            $articulo = new articulo();
            $art = $articulo->get($lineas[$i]->referencia);
            if ($art) {
               $observa = "\n" . utf8_decode($this->fix_html($art->observaciones));
            } else {
               // $observa = null; // No mostrar mensaje de error
               $observa = "\n";
            }
            if($this->impresion['print_dto'])
            {
                $lafila = array(
                    // '0' => utf8_decode($lineas[$i]->albaran_codigo() . '-' . $lineas[$i]->albaran_numero()),
                    '0' => utf8_decode($lineas[$i]->albaran_numero()),
                    '1' => utf8_decode(strtoupper($lineas[$i]->descripcion)) . $observa,
                    '2' => utf8_decode($lineas[$i]->cantidad),
                    '3' => $this->ckeckEuro($lineas[$i]->pvpunitario),
                    '4' => utf8_decode($this->show_numero($lineas[$i]->dtopor, 0) . " %"),
                    '5' => utf8_decode($this->show_numero($lineas[$i]->iva, 0) . " %"),
                    // '6' => $this->ckeckEuro($lineas[$i]->pvptotal), // Importe con Descuentos aplicados
                    '6' => $this->ckeckEuro($lineas[$i]->total_iva())
                );
            }
            else 
            {
                $lafila = array(
                    // '0' => utf8_decode($lineas[$i]->albaran_codigo() . '-' . $lineas[$i]->albaran_numero()),
                    '0' => utf8_decode($lineas[$i]->albaran_numero()),
                    '1' => utf8_decode(strtoupper($this->fix_html($lineas[$i]->descripcion))) . $observa,
                    '2' => utf8_decode($lineas[$i]->cantidad),
                    '3' => $this->ckeckEuro($lineas[$i]->pvpunitario),
                    //'4' => utf8_decode($this->show_numero($lineas[$i]->dtopor, 0) . " %"),
                    '4' => utf8_decode($this->show_numero($lineas[$i]->iva, 0) . " %"),
                    // '6' => $this->ckeckEuro($lineas[$i]->pvptotal), // Importe con Descuentos aplicados
                    '5' => $this->ckeckEuro($lineas[$i]->total_iva())
                );
            }
            $pdf_doc->Row($lafila, '1'); // Row(array, Descripcion del Articulo -- ultimo valor a imprimir)
         }
         $pdf_doc->piepagina = true;
      }

      // Damos salida al archivo PDF
      if($archivomail)
      {
         if( !file_exists('tmp/' . FS_TMP_NAME . 'enviar') )
         {
            mkdir('tmp/' . FS_TMP_NAME . 'enviar');
         }
         
         $pdf_doc->Output('tmp/' . FS_TMP_NAME . 'enviar/' . $archivomail, 'F');
      }
      else if($archivodownload)
      {
         ob_end_clean();
         $pdf_doc->Output($archivodownload, 'I');
      }
      else
      {
         ob_end_clean();
         $pdf_doc->Output();
      }
   }

   private function share_extensions() {
      $extensiones = array(
          array(
              'name' => 'factura_detallada',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_factura',
              'type' => 'pdf',
              'text' => '<span class="glyphicon glyphicon-print"></span>&nbsp; Factura detallada',
              'params' => ''
          ),
          array(
              'name' => 'email_factura_detallada',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_factura',
              'type' => 'email',
              'text' => ucfirst(FS_FACTURA) . ' detallada',
              'params' => '&factura=TRUE&tipo=detallada'
          )
      );
      foreach ($extensiones as $ext) {
         $fsext = new fs_extension($ext);
         if (!$fsext->save()) {
            $this->new_error_msg('Error al guardar la extensión ' . $ext['name']);
         }
      }
   }

   private function fix_html($txt) {
      $newt = str_replace('&lt;', '<', $txt);
      $newt = str_replace('&gt;', '>', $newt);
      $newt = str_replace('&quot;', '"', $newt);
      $newt = str_replace('&#39;', "'", $newt);
      $newt = str_replace('&#8211;', '-', $newt);
      $newt = str_replace('&#8212;', '-', $newt);
      $newt = str_replace('&#8213;', '-', $newt);
      $newt = str_replace('–', '-', $newt);
      //$newt = str_replace('€','EUR', $newt);
      return $newt;
   }

   private function enviar_email($doc, $tipo = 'detallada') 
   {
      if ($this->empresa->can_send_mail()) 
      {
         if ($_POST['email'] != $this->cliente->email AND isset($_POST['guardar']))
         {
            $this->cliente->email = $_POST['email'];
            $this->cliente->save();
         }

         if ($doc == 'factura') {
            $filename = 'factura_' . $this->factura->codigo . '.pdf';
            $this->generar_pdf($filename);
         }

         if (file_exists('tmp/' . FS_TMP_NAME . 'enviar/' . $filename)) 
         {
            $mail = $this->empresa->new_mail();
         	$mail->FromName = $this->user->get_agente_fullname();
         	$mail->addReplyTo($_POST['de'], $mail->FromName);
         	
         	$mail->addAddress($_POST['email'], $this->cliente->razonsocial);
         	if($_POST['email_copia'])
         	{
         		if( isset($_POST['cco']) )
         		{
         			$mail->addBCC($_POST['email_copia'], $this->cliente->razonsocial);
         		}
         		else
         		{
         			$mail->addCC($_POST['email_copia'], $this->cliente->razonsocial);
         		}
         	}

            if($doc == 'factura')
            {
               $mail->Subject = $this->empresa->nombre . ': Su factura ' . $this->factura->codigo;
            }
            $mail->AltBody = $_POST['mensaje'];
            $mail->msgHTML( nl2br($_POST['mensaje']) );
            $mail->isHTML(TRUE);
            
            $mail->addAttachment('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
            if( is_uploaded_file($_FILES['adjunto']['tmp_name']) )
            {
               $mail->addAttachment($_FILES['adjunto']['tmp_name'], $_FILES['adjunto']['name']);
            }
            
            if( $this->empresa->mail_connect($mail) )
            {
               if( $mail->send() )
               {
                  $this->new_message('Mensaje enviado correctamente.');
                  
                  /// nos guardamos la fecha de envío
                  if($doc == 'factura')
                  {
                     $this->factura->femail = $this->today();
                     $this->factura->save();
                  }
               }
               else
                  $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
            }
            else
               $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
            
            unlink('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
         }
         else
            $this->new_error_msg('Imposible generar el PDF.');
      }
   }

  
   private function _genera_formapago() 
   {
		$texto_pago = array();
      $fp0 = new forma_pago();
      
      $forma_pago = $fp0->get($this->factura->codpago);
      if($forma_pago)
		{
         $texto_pago[] = $forma_pago->descripcion;
			if($forma_pago->domiciliado) {
					$cbc0 = new cuenta_banco_cliente ();
					$encontrada = FALSE;
					foreach ( $cbc0->all_from_cliente ( $this->factura->codcliente ) as $cbc ) {
						$tmp_textopago = "Domiciliado en: ";
						if ($cbc->iban) {
							$texto_pago[] = $tmp_textopago. $cbc->iban ( TRUE );
						}
						
						if ($cbc->swift) {
							$texto_pago[] = "SWIFT/BIC: " . $cbc->swift;
						}
						$encontrada = TRUE;
						break;
					}
					if (! $encontrada) {
						$texto_pago[] = "Cliente sin cuenta bancaria asignada";
					}
			} else if ($forma_pago->codcuenta) {
					$cb0 = new cuenta_banco ();
					$cuenta_banco = $cb0->get ( $forma_pago->codcuenta );
					if ($cuenta_banco) {
						if ($cuenta_banco->iban) {
							$texto_pago[] = "IBAN: " . $cuenta_banco->iban ( TRUE );
						}
						
						if ($cuenta_banco->swift) {
							$texto_pago[] = "SWIFT o BIC: " . $cuenta_banco->swift;
						}
					}
			}
         
         $texto_pago[] = "Vencimiento: " . $this->factura->vencimiento;
		}
      
      return $texto_pago;
   }
}
