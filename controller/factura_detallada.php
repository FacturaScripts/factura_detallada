<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014      Valentín González    valengon@hotmail.com
 * Copyright (C) 2014-2017 Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2015-2016 César Sáez Rodríguez NATHOO@lacalidad.es
 * Copyright (C) 2016-2017 Rafael Salas         rsalas.match@gmail.com
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

class factura_detallada extends fs_controller
{

    public $cliente;
    public $factura;
    public $idioma;
    public $impresion;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Factura Detallada', 'ventas', FALSE, FALSE);
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

        $this->factura = FALSE;
        if (isset($_GET['id'])) {
            $factura = new factura_cliente();
            $this->factura = $factura->get($_GET['id']);
        }

        if ($this->factura) {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->factura->codcliente);

            if (!$this->cliente) {
                $this->new_error_msg('Cliente no encontrado.');
            } else if (isset($_POST['email'])) {
                $this->enviar_email();
            } else {
                $this->template = FALSE;

                $filename = 'factura_' . $this->factura->codigo . '.pdf';
                $this->generar_pdf(FALSE, $filename);
            }
        } else {
            $this->new_error_msg("¡Factura de cliente no encontrada!");
        }

        $this->share_extensions();
    }

    // Corregir el Bug de fpdf con el Simbolo del Euro ---> €
    public function ckeckEuro($cadena)
    {
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

    public function generar_pdf($archivomail = FALSE, $archivodownload = FALSE)
    {
        ///// INICIO - Factura Detallada
        /// Creamos el PDF y escribimos sus metadatos
        $pdf_doc = new PDF_MC_Table('P', 'mm', 'A4');
        if (!defined('EEURO'))
            define('EEURO', chr(128));
        $pdf_doc->idioma = $this->idioma;
        $pdf_doc->impresion = $this->impresion;

        $lineas = $this->factura->get_lineas();
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

        $pdf_doc->SetTitle(ucfirst($this->idioma->factura) . ': ' . $this->factura->codigo . " " . $this->factura->numero2);
        $pdf_doc->SetSubject('Factura del cliente: ' . $this->factura->nombrecliente);
        $pdf_doc->SetAuthor($this->empresa->nombre);
        $pdf_doc->SetCreator('FacturaSctipts V_' . $this->version());

        $pdf_doc->AliasNbPages();
        $pdf_doc->SetAutoPageBreak(true, 40);

        // Sacamos si es una factura rectificativa y tomamos el codigo de factura
        $pdf_doc->codserie = $this->factura->codserie;
        if ($this->factura->codigorect) {
            $pdf_doc->codigorect = $this->factura->codigorect;
        }

        // Definimos el color de relleno (gris, rojo, verde, azul)
        $pdf_doc->SetColorRelleno($this->impresion['f_detallada_color'], $this->impresion['f_detallada_color_r'], $this->impresion['f_detallada_color_g'], $this->impresion['f_detallada_color_b']);

        /// Definimos todos los datos de la cabecera de la factura
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
        $pdf_doc->fdf_tipodocumento = 'FACTURA'; // (FACTURA, FACTURA PROFORMA, ¿ALBARAN, PRESUPUESTO?...)
        $pdf_doc->fdf_codigo = $this->factura->codigo;

        // Fecha, Codigo Cliente y observaciones de la factura
        $pdf_doc->fdf_fecha = $this->factura->fecha;
        $pdf_doc->fdf_codcliente = $this->factura->codcliente;
        $pdf_doc->fdf_observaciones = $this->idioma->fix_html($this->factura->observaciones);

        // Datos del Cliente
        $pdf_doc->fdf_nombrecliente = $this->idioma->fix_html($this->factura->nombrecliente);
        $pdf_doc->fdf_FS_CIFNIF = FS_CIFNIF;
        $pdf_doc->fdf_cifnif = $this->factura->cifnif;
        $pdf_doc->fdf_direccion = $this->idioma->fix_html($this->factura->direccion);
        $pdf_doc->fdf_codpostal = $this->factura->codpostal;
        $pdf_doc->fdf_ciudad = $this->idioma->fix_html($this->factura->ciudad);
        $pdf_doc->fdf_provincia = $this->idioma->fix_html($this->factura->provincia);
        $pdf_doc->fdc_telefono1 = $this->cliente->telefono1;
        $pdf_doc->fdc_telefono2 = $this->cliente->telefono2;
        $pdf_doc->fdc_fax = $this->cliente->fax;
        $pdf_doc->fdc_email = $this->cliente->email;
        $pdf_doc->fdc_orden = $this->factura->numero2;

        $pdf_doc->fdf_epago = $pdf_doc->fdf_divisa = $pdf_doc->fdf_pais = '';

        // Forma de Pago de la Factura  
        $formapago = $this->_genera_formapago();
        $pdf_doc->fdf_epago = $formapago;

        // Divisa de la Factura
        $divisa = new divisa();
        $edivisa = $divisa->get($this->factura->coddivisa);
        if ($edivisa) {
            $pdf_doc->fdf_divisa = utf8_decode($edivisa->descripcion);
        }

        // Pais de la Factura
        $pais = new pais();
        $epais = $pais->get($this->factura->codpais);
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
                    utf8_decode(mb_strtoupper($this->idioma->iva)),
                    utf8_decode(mb_strtoupper($this->idioma->importe)),
                )
            );
            $pdf_doc->SetWidths(array(16, 102, 10, 20, 12, 12, 22));
            $pdf_doc->SetAligns(array('C', 'L', 'R', 'R', 'R', 'R', 'R'));
            $pdf_doc->SetColors(array('6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109'));
        } else {
            $pdf_doc->Setdatoscab(
                array(
                    'ALB20',
                    utf8_decode(mb_strtoupper($this->idioma->descripcion)),
                    utf8_decode(mb_strtoupper($this->idioma->cant)),
                    utf8_decode(mb_strtoupper($this->idioma->precio)),
                    utf8_decode(mb_strtoupper($this->idioma->iva)),
                    utf8_decode(mb_strtoupper($this->idioma->importe)),
                )
            );
            $pdf_doc->SetWidths(array(16, 107, 10, 20, 15, 22));
            $pdf_doc->SetAligns(array('C', 'L', 'R', 'R', 'R', 'R'));
            $pdf_doc->SetColors(array('6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109', '6|47|109'));
        }

        /// Agregamos la pagina inicial de la factura
        $pdf_doc->AddPage();

        /// Definimos todos los datos del PIE de la factura
        /// Lineas de IVA
        $lineas_iva = $this->factura->get_lineas_iva();
        if (count($lineas_iva) > 4) {
            $pdf_doc->fdf_lineasiva = $lineas_iva;
        } else {
            $filaiva = array();
            $i = 0;
            foreach ($lineas_iva as $li) {
                $i++;
                $filaiva[$i][0] = ($li->iva) ? utf8_decode($this->idioma->iva) . $li->iva : '';
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

        if ($lineas) {
            $neto = 0;
            for ($i = 0; $i < count($lineas); $i++) {
                $neto += $lineas[$i]->pvptotal;
                $pdf_doc->neto = $this->ckeckEuro($neto);

                $articulo = new articulo();
                $art = $articulo->get($lineas[$i]->referencia);
                if ($art && $this->impresion['f_detallada_observaciones_producto']) {
                    $observa = "\n" . utf8_decode($this->idioma->fix_html($art->observaciones));
                } else {
                    $observa = "\n";
                }

                if ($this->impresion['f_detallada_print_may_min']) {
                    $descripcion_retocada = $this->idioma->fix_html($lineas[$i]->descripcion) . trim($observa);
                } else {
                    $descripcion_retocada = mb_strtoupper($this->idioma->fix_html($lineas[$i]->descripcion), 'utf-8') . trim($observa);
                }

                $codigo_albaran = '';
                $añade = '';
                if ($this->impresion['f_detallada_agrupa_albaranes']) {
                    $albaran_cliente = new albaran_cliente();
                    $albaran = $albaran_cliente->get_by_codigo($lineas[$i]->albaran_codigo());
                    if ($albaran) {
                        $codigo_albaran = mb_strtoupper(FS_ALBARAN, 'utf-8') . ": " . $lineas[$i]->albaran_codigo();
                        $codigo_albaran .= " = ";
                        $añade = $this->ckeckEuro($albaran->neto);
                    }
                } else {
                    $codigo_albaran = substr($lineas[$i]->albaran_codigo(), 5, strlen($lineas[$i]->albaran_codigo()) - 5);
                    $añade = '';
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
                        '0' => utf8_decode($codigo_albaran) . $añade,
                        '1' => utf8_decode($linea_nueva),
                        '2' => utf8_decode($lineas[$i]->cantidad),
                        '3' => $this->ckeckEuro($lineas[$i]->pvpunitario),
                        '4' => utf8_decode($this->show_numero($lineas[$i]->dtopor, 1) . "%"),
                        '5' => utf8_decode($this->show_numero($lineas[$i]->iva, 1) . "%"),
                        '6' => $this->ckeckEuro($lineas[$i]->total_iva())
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
                        '0' => utf8_decode($codigo_albaran) . $añade,
                        '1' => utf8_decode($linea_nueva),
                        '2' => utf8_decode($lineas[$i]->cantidad),
                        '3' => $this->ckeckEuro($lineas[$i]->pvpunitario),
                        '4' => utf8_decode($this->show_numero($lineas[$i]->iva, 1) . "%"),
                        '5' => $this->ckeckEuro($lineas[$i]->total_iva())
                    );
                }
                if (($i + 1) < count($lineas)) {
                    $pdf_doc->Row($lafila, '1', true, $lineas[$i]->mostrar_cantidad, $lineas[$i]->mostrar_precio); // Row(array, Descripcion del Articulo -- ultimo valor a imprimir)
                } else {
                    $pdf_doc->Row($lafila, '1', false, $lineas[$i]->mostrar_cantidad, $lineas[$i]->mostrar_precio); // Row(array, Descripcion del Articulo -- ultimo valor a imprimir)
                }
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
            $fsext->name = 'factura_detallada_' . $idi->codidioma;
            $fsext->from = __CLASS__;
            $fsext->to = 'ventas_factura';
            $fsext->type = 'pdf';
            $fsext->text = '<span class="glyphicon glyphicon-print"></span>&nbsp; Factura detallada ' . $idi->codidioma;
            $fsext->params = '&codidioma=' . $idi->codidioma;

            if ($idi->activo) {
                $fsext->save();
            } else {
                $fsext->delete();
            }

            $fsext2 = new fs_extension();
            $fsext2->name = 'enviar_factura_detallada_' . $idi->codidioma;
            $fsext2->from = __CLASS__;
            $fsext2->to = 'ventas_factura';
            $fsext2->type = 'email';
            $fsext2->text = 'Factura detallada ' . $idi->codidioma;
            $fsext2->params = '&codidioma=' . $idi->codidioma;

            if ($idi->activo) {
                $fsext2->save();
            } else {
                $fsext2->delete();
            }
        }

        /// eliminamos las antiguas extensiones
        $fsext = new fs_extension();
        $fsext->name = 'factura_detallada';
        $fsext->from = __CLASS__;
        $fsext->delete();

        $fsext2 = new fs_extension();
        $fsext2->name = 'enviar_factura_detallada';
        $fsext2->from = __CLASS__;
        $fsext2->delete();
    }

    private function enviar_email()
    {
        if ($this->empresa->can_send_mail()) {
            if ($this->cliente && isset($_POST['guardar']) && $_POST['email'] != $this->cliente->email) {
                $this->cliente->email = $_POST['email'];
                $this->cliente->save();
            }

            $filename = 'factura_' . $this->factura->codigo . '.pdf';
            $this->generar_pdf($filename);

            if (file_exists('tmp/' . FS_TMP_NAME . 'enviar/' . $filename)) {
                $mail = $this->empresa->new_mail();
                $mail->FromName = $this->user->get_agente_fullname();
                $mail->addReplyTo($_POST['de'], $mail->FromName);

                $mail->addAddress($_POST['email'], $this->cliente->razonsocial);
                if ($_POST['email_copia']) {
                    if (isset($_POST['cco'])) {
                        $mail->addBCC($_POST['email_copia'], $this->cliente->razonsocial);
                    } else {
                        $mail->addCC($_POST['email_copia'], $this->cliente->razonsocial);
                    }
                }

                $mail->Subject = $this->empresa->nombre . ': Su factura ' . $this->factura->codigo;
                $mail->AltBody = $_POST['mensaje'];
                $mail->msgHTML(nl2br($_POST['mensaje']));
                $mail->isHTML(TRUE);

                $mail->addAttachment('tmp/' . FS_TMP_NAME . 'enviar/' . $filename);
                if (is_uploaded_file($_FILES['adjunto']['tmp_name'])) {
                    $mail->addAttachment($_FILES['adjunto']['tmp_name'], $_FILES['adjunto']['name']);
                }

                if ($this->empresa->mail_connect($mail) && $mail->send()) {
                    $this->new_message('Mensaje enviado correctamente.');

                    /// nos guardamos la fecha de envío
                    $this->factura->femail = $this->today();
                    $this->factura->save();
                } else {
                    $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
                }

                unlink('tmp/' . FS_TMP_NAME . 'enviar/' . $filename);
            } else {
                $this->new_error_msg('Imposible generar el PDF.');
            }
        }
    }

    private function _genera_formapago()
    {
        $texto_pago = array();
        $fp0 = new forma_pago();

        $forma_pago = $fp0->get($this->factura->codpago);
        if ($forma_pago) {
            $texto_pago[] = $forma_pago->descripcion;
            if ($forma_pago->imprimir) {
                if ($forma_pago->domiciliado) {
                    $cbc0 = new cuenta_banco_cliente ();
                    $encontrada = FALSE;
                    foreach ($cbc0->all_from_cliente($this->factura->codcliente) as $cbc) {
                        $tmp_textopago = "Domiciliado en: ";
                        if ($cbc->iban) {
                            $texto_pago[] = $tmp_textopago . $cbc->iban(TRUE);
                        }

                        if ($cbc->swift) {
                            $texto_pago[] = "SWIFT/BIC: " . $cbc->swift;
                        }
                        $encontrada = TRUE;
                        break;
                    }
                    if (!$encontrada) {
                        $texto_pago[] = "Cliente sin cuenta bancaria asignada";
                    }
                } else if ($forma_pago->codcuenta) {
                    $cb0 = new cuenta_banco ();
                    $cuenta_banco = $cb0->get($forma_pago->codcuenta);
                    if ($cuenta_banco) {
                        if ($cuenta_banco->iban) {
                            $texto_pago[] = "IBAN: " . $cuenta_banco->iban(TRUE);
                        }

                        if ($cuenta_banco->swift) {
                            $texto_pago[] = "SWIFT o BIC: " . $cuenta_banco->swift;
                        }
                    }
                }
                $texto_pago[] = utf8_decode(ucfirst($this->idioma->vencimiento)) . ": " . $this->factura->vencimiento;
            }
        }
        return $texto_pago;
    }
}
