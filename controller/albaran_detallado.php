<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2016-2017 Rafael Salas         rsalas.match@gmail.com
 * Copyright (C) 2017      Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once __DIR__ . '/../fpdf17/fs_fpdf.php';

if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', __DIR__ . '/../fpdf17/font/');
}

require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';

class albaran_detallado extends fs_controller
{

    public $albaran;
    public $cliente;
    public $idioma;
    public $impresion;

    public function __construct()
    {
        parent::__construct(__CLASS__, ucfirst(FS_ALBARAN) . ' Detallado', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        /// obtenemos los datos de configuración de impresión
        $fsvar = new fs_var();
        $this->impresion = $fsvar->array_get(
            array(
            'print_ref' => '1',
            'print_dto' => '1',
            'print_alb' => '0',
            'print_formapago' => '1',
            'f_detallada_color' => 'azul',
            'f_detallada_color_r' => '190',
            'f_detallada_color_g' => '0',
            'f_detallada_color_b' => '0',
            'f_detallada_print_may_min' => FALSE,
            'f_detallada_QRCODE' => FALSE,
            'f_detallada_observaciones_producto' => FALSE,
            'f_detallada_imprime_albaran' => FALSE,
            'f_detallada_agrupa_albaranes' => FALSE,
            'f_detallada_maquetar_negrita' => FALSE
            ), FALSE
        );

        /// cargamos el idioma
        $idi0 = new idioma_fac_det();
        if (isset($_REQUEST['codidioma'])) {
            $this->idioma = $idi0->get($_REQUEST['codidioma']);
        } else {
            foreach ($idi0->all() as $idi) {
                $this->idioma = $idi;
                break;
            }
        }

        $this->albaran = FALSE;
        if (isset($_GET['id'])) {
            $albaran = new albaran_cliente();
            $this->albaran = $albaran->get($_GET['id']);
        }

        if ($this->albaran) {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->albaran->codcliente);

            if (!$this->cliente) {
                $this->new_error_msg('Cliente no encontrado.');
            } else if (isset($_POST['email'])) {
                $this->enviar_email('albaran', $_REQUEST['tipo']);
            } else {
                $filename = 'albaran_' . $this->albaran->codigo . '.pdf';
                $this->generar_albaran_pdf(FALSE, $filename);
            }
        } else {
            $this->new_error_msg("¡" . ucfirst(FS_ALBARAN) . " de cliente no encontrada!");
        }

        $this->share_extensions();
    }

    // Corregir el Bug de fpdf con el Simbolo del Euro ---> €
    public function ckeckEuro($cadena)
    {
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
    public function generar_albaran_pdf($archivomail = FALSE, $archivodownload = FALSE)
    {
        $this->template = FALSE;

        ///// INICIO - Factura Detallada
        /// Creamos el PDF y escribimos sus metadatos
        $pdf_doc = new PDF_MC_Table('P', 'mm', 'A4');
        if (!defined('EEURO')) {
            define('EEURO', chr(128));
        }
        $pdf_doc->idioma = $this->idioma;
        $pdf_doc->impresion = $this->impresion;

        $lineas = $this->albaran->get_lineas();
        $pdf_doc->numero_lineas = count($lineas);

        if ($this->impresion['print_dto']) {
            $this->impresion['print_dto'] = FALSE;

            /// leemos las líneas para ver si de verdad mostramos los descuentos
            foreach ($lineas as $lin) {
                if ($lin->dtopor != 0) {
                    $this->impresion['print_dto'] = TRUE;
                    break;
                }
            }
        }

        $pdf_doc->SetTitle(ucfirst($this->idioma->albaran) . ': ' . $this->albaran->codigo . " " . $this->albaran->numero2);
        $pdf_doc->SetSubject('Albarán del cliente: ' . $this->albaran->nombrecliente);
        $pdf_doc->SetAuthor($this->empresa->nombre);
        $pdf_doc->SetCreator('FacturaSctipts V_' . $this->version());

        $pdf_doc->AliasNbPages();
        $pdf_doc->SetAutoPageBreak(true, 40);
        $pdf_doc->es_fatura = false;

        // Sacamos si es una factura rectificativa y tomamos el codigo de factura
        $pdf_doc->codserie = $this->albaran->codserie;

        // Definimos el color de relleno (gris, rojo, verde, azul)
        $pdf_doc->SetColorRelleno($this->impresion['f_detallada_color'], $this->impresion['f_detallada_color_r'], $this->impresion['f_detallada_color_g'], $this->impresion['f_detallada_color_b']);

        /// Definimos todos los datos de la cabecera del albaran
        /// Datos de la empresa
        $pdf_doc->fde_nombre = $this->idioma->fix_html($this->empresa->nombre);
        $pdf_doc->fde_FS_CIFNIF = FS_CIFNIF;
        $pdf_doc->fde_cifnif = $this->empresa->cifnif;
        $pdf_doc->fde_direccion = $this->empresa->direccion;
        $pdf_doc->fde_codpostal = $this->empresa->codpostal;
        $pdf_doc->fde_ciudad = $this->empresa->ciudad;
        $pdf_doc->fde_provincia = $this->empresa->provincia;

        $pdf_doc->fde_telefono = '';
        if ($this->empresa->telefono) {
            $pdf_doc->fde_telefono = ucfirst($this->idioma->fix_html($this->idioma->telefono)) . ': ' . $this->empresa->telefono;
        }

        $pdf_doc->fde_fax = '';
        if ($this->empresa->fax) {
            $pdf_doc->fde_fax = ucfirst($this->idioma->fix_html($this->idioma->fax)) . ': ' . $this->empresa->fax;
        }

        $pdf_doc->fde_email = $this->empresa->email;
        $pdf_doc->fde_web = $this->empresa->web;
        $pdf_doc->fde_piefactura = $this->empresa->pie_factura;

        /// Insertamos el Logo y Marca de Agua
        if (file_exists(FS_MYDOCS . 'images/logo.png') OR file_exists(FS_MYDOCS . 'images/logo.jpg')) {
            $pdf_doc->fdf_verlogotipo = '1'; // 1/0 --> Mostrar Logotipo
            $pdf_doc->fdf_Xlogotipo = '15'; // Valor X para Logotipo
            $pdf_doc->fdf_Ylogotipo = '35'; // Valor Y para Logotipo
            $pdf_doc->fdf_vermarcaagua = '1'; // 1/0 --> Mostrar Marca de Agua
            $pdf_doc->fdf_Xmarcaagua = '25'; // Valor X para Marca de Agua
            $pdf_doc->fdf_Ymarcaagua = '110'; // Valor Y para Marca de Agua
        } else {
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
        $pdf_doc->fdf_observaciones = $this->idioma->fix_html($this->albaran->observaciones);

        // Datos del Cliente
        $pdf_doc->fdf_nombrecliente = $this->idioma->fix_html($this->albaran->nombrecliente);
        $pdf_doc->fdf_FS_CIFNIF = FS_CIFNIF;
        $pdf_doc->fdf_cifnif = $this->albaran->cifnif;
        $pdf_doc->fdf_direccion = $this->idioma->fix_html($this->albaran->direccion);
        $pdf_doc->fdf_codpostal = $this->albaran->codpostal;
        $pdf_doc->fdf_ciudad = $this->albaran->ciudad;
        $pdf_doc->fdf_provincia = $this->albaran->provincia;
        $pdf_doc->fdc_telefono1 = $this->cliente->telefono1;
        $pdf_doc->fdc_telefono2 = $this->cliente->telefono2;
        $pdf_doc->fdc_fax = $this->cliente->fax;
        $pdf_doc->fdc_email = $this->cliente->email;

        $pdf_doc->fdf_contacto = array();
        if (isset($this->albaran->persona_contacto)) {
            $pdf_doc->fdf_contacto[] = "Persona de contacto: " . $this->albaran->persona_contacto;
        }
        if (isset($this->albaran->actuacion_en)) {
            $pdf_doc->fdf_contacto[] = "Actuación En: " . $this->albaran->actuacion_en;
        }
        if (isset($this->cliente->numeroproveedor)) {
            $pdf_doc->fdf_contacto[] = "Número Proveedor: " . $this->cliente->numeroproveedor;
        }
        if ($this->albaran->numero2) {
            $pdf_doc->fdf_contacto[] = ucfirst($this->idioma->pedido) . ": " . $this->albaran->numero2;
        }

        // Divisa de la Factura
        $divisa = new divisa();
        $edivisa = $divisa->get($this->albaran->coddivisa);
        if ($edivisa) {
            $pdf_doc->fdf_divisa = utf8_decode($edivisa->descripcion);
        }

        // Pais de la Factura
        $pais = new pais();
        $epais = $pais->get($this->albaran->codpais);
        if ($epais) {
            $pdf_doc->fdf_pais = $this->idioma->fix_html($epais->nombre);
        }

        // Cabecera Titulos Columnas
        if ($this->impresion['print_dto']) {
            $pdf_doc->Setdatoscab(
                array(
                    'ALB20',
                    utf8_decode(mb_strtoupper($this->idioma->descripcion)),
                    utf8_decode(mb_strtoupper($this->idioma->cant)),
                    utf8_decode(mb_strtoupper($this->idioma->precio)),
                    utf8_decode(mb_strtoupper($this->idioma->dto)),
                    utf8_decode(mb_strtoupper($this->idioma->importe)),
                )
            );
            $pdf_doc->SetWidths(array(16, 112, 10, 20, 10, 22));
            $pdf_doc->SetAligns(array('C', 'L', 'R', 'R', 'R', 'R'));
            $pdf_doc->SetColors(array('6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109'));
        } else {
            $pdf_doc->Setdatoscab(
                array(
                    'ALB20',
                    utf8_decode(mb_strtoupper($this->idioma->descripcion)),
                    utf8_decode(mb_strtoupper($this->idioma->cant)),
                    utf8_decode(mb_strtoupper($this->idioma->precio)),
                    utf8_decode(mb_strtoupper($this->idioma->importe)),
                )
            );
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
                $obse_prod = $this->impresion["f_detallada_observaciones_producto"];
                if ($art && $obse_prod) {
                    $observa = "\n" . utf8_decode($this->idioma->fix_html($art->observaciones));
                } else {
                    $observa = "\n";
                }

                $may_min = $this->impresion["f_detallada_print_may_min"];
                if ($may_min) {
                    $descripcion_retocada = $this->idioma->fix_html($lineas[$i]->descripcion) . trim($observa);
                } else {
                    $descripcion_retocada = mb_strtoupper($this->idioma->fix_html($lineas[$i]->descripcion), 'utf-8') . trim($observa);
                }

                if ($this->impresion['print_dto']) {
                    $array_descripcion = explode("\n", $descripcion_retocada);
                    if (count($array_descripcion) <= 0) {
                        $linea_nueva = $descripcion_retocada;
                    } else {
                        $linea_nueva = "";
                        $num_lineas = count($array_descripcion);
                        $linea_veririficada = 1;
                        foreach ($array_descripcion as $linea_descripcion) {
                            $linea_nueva = $linea_nueva . $linea_descripcion;
                            if ($linea_veririficada <> $num_lineas) {
                                $linea_nueva = $linea_nueva . "\n";
                            }
                            $linea_veririficada++;
                        }
                    }
                    $lafila = array(
                        '0' => utf8_decode($this->albaran->codigo),
                        '1' => utf8_decode($linea_nueva),
                        '2' => utf8_decode($lineas[$i]->cantidad),
                        '3' => $this->ckeckEuro($lineas[$i]->pvpunitario),
                        '4' => utf8_decode($this->show_numero($lineas[$i]->dtopor, 1) . " %"),
                        '5' => $this->ckeckEuro($lineas[$i]->pvptotal)
                    );
                } else {
                    $array_descripcion = explode("\n", $descripcion_retocada);
                    if (count($array_descripcion) <= 0) {
                        $linea_nueva = $descripcion_retocada;
                    } else {
                        $linea_nueva = "";
                        $num_lineas = count($array_descripcion);
                        $linea_veririficada = 1;
                        foreach ($array_descripcion as $linea_descripcion) {
                            $linea_nueva = $linea_nueva . $linea_descripcion;
                            if ($linea_veririficada <> $num_lineas) {
                                $linea_nueva = $linea_nueva . "\n";
                            }
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
                if (($i + 1) < count($lineas)) {
                    $pdf_doc->Row($lafila, '1', true, $lineas[$i]->mostrar_cantidad, $lineas[$i]->mostrar_precio); // Row(array, Descripcion del Articulo -- ultimo valor a imprimir)
                } else {
                    $pdf_doc->Row($lafila, '1', false, $lineas[$i]->mostrar_cantidad, $lineas[$i]->mostrar_precio);
                } // Row(array, Descripcion del Articulo -- ultimo valor a imprimir)
            }
            $pdf_doc->piepagina = true;
        }

        // Damos salida al archivo PDF
        if ($archivomail) {
            if (!file_exists('tmp/' . FS_TMP_NAME . 'enviar')) {
                mkdir('tmp/' . FS_TMP_NAME . 'enviar');
            }

            $pdf_doc->Output('tmp/' . FS_TMP_NAME . 'enviar/' . $archivomail, 'F');
        } else if ($archivodownload) {
            if (ob_get_contents()) {
                ob_end_clean();
            }
            $pdf_doc->Output($archivodownload, 'I');
        } else {
            if (ob_get_contents()) {
                ob_end_clean();
            }
            $pdf_doc->Output();
        }
    }

    private function share_extensions()
    {
        foreach ($this->idioma->all() as $idi) {
            $fsext = new fs_extension();
            $fsext->name = 'albaran_detallado_' . $idi->codidioma;
            $fsext->from = __CLASS__;
            $fsext->to = 'ventas_albaran';
            $fsext->type = 'pdf';
            $fsext->text = '<span class="glyphicon glyphicon-print"></span>&nbsp; ' . ucfirst(FS_ALBARAN) . ' detallado ' . $idi->codidioma;
            $fsext->params = '&codidioma=' . $idi->codidioma;

            if ($idi->activo) {
                $fsext->save();
            } else {
                $fsext->delete();
            }
        }

        /// eliminamos las antiguas extensiones
        $fsext = new fs_extension();
        $fsext->name = 'albaran_detallado';
        $fsext->from = __CLASS__;
        $fsext->delete();
    }
}
