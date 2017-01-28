<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once 'plugins/factura_detallada/fpdf17/fs_fpdf.php';
define('FPDF_FONTPATH', 'plugins/factura_detallada/fpdf17/font/');

require_model('cliente.php');
require_model('albaran_cliente.php');
require_model('articulo.php');
require_model('divisa.php');
require_model('pais.php');
require_model('forma_pago.php');
require_model('cuenta_banco.php');
require_model('cuenta_banco_cliente.php');
require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';

class albaran_detallado extends fs_controller {

   public $cliente;
   public $albaran;
   public $impresion;

   public function __construct() {
      parent::__construct(__CLASS__, 'Albaran Detallado', 'ventas', FALSE, FALSE);
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

      $this->albaran = FALSE;
      if (isset($_GET['id'])) {
         $albaran = new albaran_cliente();
         $this->albaran = $albaran->get($_GET['id']);
      }

      if ($this->albaran) {
         $cliente = new cliente();
         $this->cliente = $cliente->get($this->albaran->codcliente);

         if (isset($_POST['email'])) {
            $this->enviar_email('albaran', $_REQUEST['tipo']);
         } else {
            $filename = 'albaran_' . $this->albaran->codigo . '.pdf';
            $this->generar_albaran_pdf(FALSE, $filename);
         }
      } else {
         $this->new_error_msg("¡Albaran de cliente no encontrada!");
      }
   }

   // Corregir el Bug de fpdf con el Simbolo del Euro ---> €
   public function ckeckEuro($cadena) {
      $mostrar = $this->show_precio($cadena, $this->albaran->coddivisa);
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

   /**
    * generar_albaran_pdf: Generamos un pdf detallado del albarán seleccionado
    * @param type $archivomail
    * @param type $archivodownload
    */
   public function generar_albaran_pdf($archivomail = FALSE, $archivodownload = FALSE) {
      ///// INICIO - Factura Detallada
      /// Creamos el PDF y escribimos sus metadatos
      $pdf_doc = new PDF_MC_Table('P', 'mm', 'A4');
      define('EEURO', chr(128));
      $lineas = $this->albaran->get_lineas();
      $pdf_doc->numero_lineas = count($lineas);
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

      $pdf_doc->SetTitle('Albarán: ' . $this->albaran->codigo . " " . $this->albaran->numero2);
      $pdf_doc->SetSubject('Albarán del cliente: ' . $this->albaran->nombrecliente);
      $pdf_doc->SetAuthor($this->empresa->nombre);
      $pdf_doc->SetCreator('FacturaSctipts V_' . $this->version());

      $pdf_doc->AliasNbPages();
      $pdf_doc->SetAutoPageBreak(true, 40);
      $pdf_doc->es_fatura = false;

      // Sacamos si es una factura rectificativa y tomamos el codigo de factura
      $pdf_doc->codserie = $this->albaran->codserie;

      // Definimos el color de relleno (gris, rojo, verde, azul)
      /// cargamos la configuración
      $fsvar = new fs_var();
      $color = $fsvar->simple_get("f_detallada_color");
      if ($color) {
      	$pdf_doc->SetColorRelleno($color);
        $pdf_doc->color_rellono = $color;
      } else {
      	$pdf_doc->SetColorRelleno('azul');
        $pdf_doc->color_rellono = 'azul';
      }

      /// Definimos todos los datos de la cabecera del albaran
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
      $pdf_doc->fdf_tipodocumento = 'ALBARAN'; // (FACTURA, FACTURA PROFORMA, ¿ALBARAN, PRESUPUESTO?...)
      $pdf_doc->fdf_codigo = $this->albaran->codigo;

      // Fecha, Codigo Cliente y observaciones de la factura
      $pdf_doc->fdf_fecha = $this->albaran->fecha;
      $pdf_doc->fdf_codcliente = $this->albaran->codcliente;
      $pdf_doc->fdf_observaciones = iconv("UTF-8", "CP1252", $this->fix_html($this->albaran->observaciones));

      // Datos del Cliente
      $pdf_doc->fdf_nombrecliente = $this->fix_html($this->albaran->nombrecliente);
      $pdf_doc->fdf_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fdf_cifnif = $this->albaran->cifnif;
      $pdf_doc->fdf_direccion = $this->fix_html($this->albaran->direccion);
      $pdf_doc->fdf_codpostal = $this->albaran->codpostal;
      $pdf_doc->fdf_ciudad = $this->albaran->ciudad;
      $pdf_doc->fdf_provincia = $this->albaran->provincia;
      $pdf_doc->fdc_telefono1 = $this->cliente->telefono1;
      $pdf_doc->fdc_telefono2 = $this->cliente->telefono2;
      $pdf_doc->fdc_fax = $this->cliente->fax;
      $pdf_doc->fdc_email = $this->cliente->email;
      $pdf_doc->fdc_orden = $this->albaran->numero2;

      $pdf_doc->fdf_contacto = array();
      if(isset($this->albaran->persona_contacto))
         $pdf_doc->fdf_contacto[] = "Persona de contacto: " . $this->albaran->persona_contacto;
      if(isset($this->albaran->actuacion_en))
         $pdf_doc->fdf_contacto[] = "Actuación En: " . $this->albaran->persona_contacto;
      if(isset($this->cliente->numeroproveedor))
         $pdf_doc->fdf_contacto[] = "Número Proveedor: " . $this->cliente->numeroproveedor;
      
      // Divisa de la Factura
      $divisa = new divisa();
      $edivisa = $divisa->get($this->albaran->coddivisa);
      if ($edivisa) {
         $pdf_doc->fdf_divisa = $edivisa->descripcion;
      }

      // Pais de la Factura
      $pais = new pais();
      $epais = $pais->get($this->albaran->codpais);
      if ($epais) {
         $pdf_doc->fdf_pais = $epais->nombre;
      }
      
      // Cabecera Titulos Columnas
      if($this->impresion['print_dto'])
      {
        $pdf_doc->Setdatoscab(array('ALB20', 'DESCRIPCION', 'CANT', 'PRECIO', 'DTO', 'IMPORTE'));
        $pdf_doc->SetWidths(array(16, 112, 10, 20, 10, 22));
        $pdf_doc->SetAligns(array('C', 'L', 'R', 'R', 'R', 'R'));
        $pdf_doc->SetColors(array('6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109'));
      }
      else
      {
        $pdf_doc->Setdatoscab(array('ALB20', 'DESCRIPCION', 'CANT', 'PRECIO', 'IMPORTE'));
        $pdf_doc->SetWidths(array(16, 122, 10, 20, 22));
        $pdf_doc->SetAligns(array('C', 'L', 'R', 'R', 'R'));
        $pdf_doc->SetColors(array('6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109'));
      }

      /// Agregamos la pagina inicial de la factura
      $pdf_doc->es_factura = false;
      $pdf_doc->AddPage();

      // Total factura numerico
      $pdf_doc->fdf_numtotal = $this->ckeckEuro($this->albaran->neto);

      // Total factura numeros a texto
      $pdf_doc->fdf_textotal = $this->albaran->neto;

      if ($lineas) {
         $neto = 0;
         for ($i = 0; $i < count($lineas); $i++) {
            $neto += $lineas[$i]->pvptotal;
            $pdf_doc->neto = $this->ckeckEuro($neto);

            $articulo = new articulo();
            $art = $articulo->get($lineas[$i]->referencia);
            $obse_prod = $fsvar->simple_get("f_detallada_observaciones_producto");
            if ($art && $obse_prod) {
               $observa = "\n" . utf8_decode($this->fix_html($art->observaciones));
            } else {
               $observa = "\n";
            }

            $may_min = $fsvar->simple_get("f_detallada_print_may_min");
            if($may_min)
               $descripcion_retocada = $this->fix_html($lineas[$i]->descripcion) . trim($observa);
            else
               $descripcion_retocada = mb_strtoupper($this->fix_html($lineas[$i]->descripcion),'utf-8') . trim($observa);

            if($this->impresion['print_dto'])
            {
                $array_descripcion = explode("\n", $descripcion_retocada);
                if(count($array_descripcion) <= 0)
                    $linea_nueva = $descripcion_retocada;
                else
                {
                    $linea_nueva = "";
                    $num_lineas = count($array_descripcion);
                    $linea_veririficada = 1;
                    foreach($array_descripcion as $linea_descripcion)
                    {
                        $linea_nueva = $linea_nueva . $linea_descripcion;
                        if($linea_veririficada <> $num_lineas)
                           $linea_nueva = $linea_nueva . "\n";
                        $linea_veririficada++;
                    }
                }
                $lafila = array(
                    '0' => utf8_decode($this->albaran->codigo),
                    '1' => utf8_decode($linea_nueva),
                    '2' => utf8_decode($lineas[$i]->cantidad),
                    '3' => $this->ckeckEuro($lineas[$i]->pvpunitario),
                    '4' => utf8_decode($this->show_numero($lineas[$i]->dtopor, 0) . " %"),
                    '5' => $this->ckeckEuro($lineas[$i]->pvptotal)
                );
            }
            else 
            {
                $array_descripcion = explode("\n", $descripcion_retocada);
                if(count($array_descripcion) <= 0)
                    $linea_nueva = $descripcion_retocada;
                else
                {
                    $linea_nueva = "";
                    $num_lineas = count($array_descripcion);
                    $linea_veririficada = 1;
                    foreach($array_descripcion as $linea_descripcion)
                    {
                        $linea_nueva = $linea_nueva . $linea_descripcion;
                        if($linea_veririficada <> $num_lineas)
                           $linea_nueva = $linea_nueva . "\n";
                        $linea_veririficada++;
                    }
                }
                $lafila = array(
                    '0' => utf8_decode($this->albaran->codigo),
                    '1' => utf8_decode($linea_nueva),
                    '2' => utf8_decode($lineas[$i]->cantidad),
                    '3' => $this->ckeckEuro($lineas[$i]->pvpunitario),
                    '4' => $this->ckeckEuro($lineas[$i]->pvptotal)
                );
            }
            if(($i+1) < count($lineas))
               $pdf_doc->Row($lafila, '1', true, $lineas[$i]->mostrar_cantidad, $lineas[$i]->mostrar_precio); // Row(array, Descripcion del Articulo -- ultimo valor a imprimir)
            else
               $pdf_doc->Row($lafila, '1', false, $lineas[$i]->mostrar_cantidad, $lineas[$i]->mostrar_precio); // Row(array, Descripcion del Articulo -- ultimo valor a imprimir)
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

   private function share_extensions() {
      $extensiones = array(
          array(
              'name' => 'albaran_detallado',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_albaran',
              'type' => 'pdf',
              'text' => '<span class="glyphicon glyphicon-print"></span>&nbsp; Albaran detallado',
              'params' => ''
          )
      );
      foreach ($extensiones as $ext) {
         $fsext = new fs_extension($ext);
         if (!$fsext->save()) {
            $this->new_error_msg('Error al guardar la extensión ' . $ext['name']);
         }
      }
   }

}