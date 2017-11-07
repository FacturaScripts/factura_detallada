<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014      Valentín González    valengon@hotmail.com 
 * Copyright (C) 2014-2015 Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2016-2017 Rafael Salas  rsalas.match@gmail.com
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

require_once __DIR__ . '/fpdf.php';
require_once __DIR__ . '/../qrcode/qrcode.class.php';

class PDF_MC_Table extends FPDF
{

    public $albaran_anterior;
    public $aligns;
    public $angle = 0;
    public $codigorect;
    public $colores;
    public $color_relleno;
    public $datoscab;
    public $es_factura = true;
    public $extgstates = array();
    public $fdc_orden = '';
    public $fdf_tipodocumento = 'FACTURA';
    public $hoja_actual = 0;
    public $hoja_nueva = 0;
    public $idioma;
    public $impresion;
    public $lineaactual = 0;
    public $numero_lineas = 0;
    public $observaciones_nueva_hoja = false;
    public $piepagina = false;
    public $widths;

    function Setdatoscab($v)
    {
        //Set the array
        $this->datoscab = $v;
    }

    function SetWidths($w)
    {
        //Set the array
        $this->widths = $w;
    }

    function SetAligns($a)
    {
        //Set the array
        $this->aligns = $a;
    }

    function SetColors($a)
    {
        for ($i = 0; $i < count($a); $i++) {
            $datos = explode('|', $a[$i]);
            $this->colores[$i][0] = $datos[0];
            $this->colores[$i][1] = $datos[1];
            $this->colores[$i][2] = $datos[2];
        }
    }

    function SetColorRelleno($a, $r = '192', $g = null, $b = null)
    {
        if ($a == 'rojo') {
            $this->SetFillColor(253, 120, 120);
        } else if ($a == 'verde') {
            $this->SetFillColor(120, 253, 165);
        } else if ($a == 'azul') {
            $this->SetFillColor(120, 158, 253);
        } else if ($a == 'naranja') {
            $this->SetFillColor(255, 141, 0);
        } else if ($a == 'amarillo') {
            $this->SetFillColor(255, 255, 0);
        } else if ($a == 'marron') {
            $this->SetFillColor(100, 50, 0);
        } else if ($a == 'blanco') {
            $this->SetFillColor(245, 245, 245);
        } else if ($a == 'personalizado') {
            $this->SetFillColor($r, $g, $b);
        } else {
            $this->SetFillColor(192); // Por defecto Gris
        }

        $this->color_relleno = $a;
    }

    //Cabecera de pagina
    function Header()
    {
        // Creamos el recuadro de la empresa
        if ($this->es_factura) {
            $this->RoundedRect(9, 6, 100, 28, 3.5, 'DF');
        } else
            $this->RoundedRect(9, 6, 100, 28, 3.5, 'D');

        // Datos de la empresa
        $direccion = $this->fde_FS_CIFNIF . ": " . utf8_decode($this->fde_cifnif) . "\n" . $this->fde_direccion;
        if ($this->fde_codpostal && $this->fde_ciudad) {
            $direccion .= "\n" . $this->fde_codpostal . ' - ' . $this->fde_ciudad;
        } else {
            if ($this->fde_codpostal) {
                $direccion .= "\n" . $this->fde_codpostal;
            }
            if ($this->fde_ciudad) {
                $direccion .= "\n" . $this->fde_ciudad;
            }
        }
        if ($this->fde_provincia) {
            $direccion .= ' (' . $this->fde_provincia . ')';
        }
        if ($this->fde_telefono) {
            $direccion .= "\n" . $this->fde_telefono;
        }
        if ($this->fde_fax) {
            $direccion .= "\n" . $this->fde_fax;
        }
        $this->addSociete(utf8_decode($this->fde_nombre), utf8_decode($direccion), utf8_decode($this->fde_email), utf8_decode($this->fde_web));

        if ($this->impresion['f_detallada_QRCODE']) {
            $cadena = 'BEGIN:VCARD' . "\n";
            $cadena .= 'VERSION:2.1' . "\n";
            $cadena .= 'ORG:' . $this->fde_nombre . "\n";
            $cadena .= 'ADR;WORK:;;' . $this->fde_direccion . ';' . $this->fde_ciudad . ';;' . $this->fde_codpostal . "\n";
            $cadena .= 'EMAIL:' . $this->fde_email . "\n";
            $cadena .= 'END:VCARD';

            $qrcode = new QRcode($cadena, 'L');
            $qrcode->disableBorder();
            $background = array(255, 255, 255);
            $color = array(0, 0, 0);
            $qrcode->displayFPDF($this, 85, 10, 20, $background, $color);
            unset($qrcode);
            $this->SetColorRelleno($this->color_relleno);
        }

        // Añado si es rectificativa la info sobre la factura
        if ($this->codigorect) {
            $this->SetTextColor(255, 0, 0);
            $this->SetXY(150, 88);
            $this->SetFont('Arial', '', 12);
            $this->RotatedText(109, 92, 'F. RECT.: ' . $this->codigorect, 90);
            $this->SetTextColor(0);
        }

        //Logotipo
        if ($this->fdf_verlogotipo == '1') {
            if (file_exists(FS_MYDOCS . 'images/logo.png')) {
                list($ancho, $alto) = getimagesize(FS_MYDOCS . 'images/logo.png');
                $factor_tamano_ancho = 45 / $alto;
                $factor_tamano_alto = 90 / $ancho;
                $total_ancho = $factor_tamano_ancho * $ancho;
                $total_alto = $factor_tamano_alto * $alto;
                if ($total_alto > 45) {
                    $this->Image(FS_MYDOCS . 'images/logo.png', $this->fdf_Xlogotipo, $this->fdf_Ylogotipo, $total_ancho, 45);
                } else
                    $this->Image(FS_MYDOCS . 'images/logo.png', $this->fdf_Xlogotipo, $this->fdf_Ylogotipo, 90, $total_alto);
            }
            else if (file_exists(FS_MYDOCS . 'images/logo.jpg')) {
                list($ancho, $alto) = getimagesize(FS_MYDOCS . 'images/logo.jpg');
                $factor_tamano_ancho = 45 / $alto;
                $factor_tamano_alto = 90 / $ancho;
                $total_ancho = $factor_tamano_ancho * $ancho;
                $total_alto = $factor_tamano_alto * $alto;
                if ($total_alto > 45) {
                    $this->Image(FS_MYDOCS . 'images/logo.jpg', $this->fdf_Xlogotipo, $this->fdf_Ylogotipo, $total_ancho, 45);
                } else
                    $this->Image(FS_MYDOCS . 'images/logo.jpg', $this->fdf_Xlogotipo, $this->fdf_Ylogotipo, 90, $total_alto);
            }

            $this->Ln(0);
        }

        //Marca de agua
        if ($this->fdf_vermarcaagua == '1') {
            // set alpha to semi-transparency
            $this->SetAlpha(0.05);

            // draw png image
            if (file_exists(FS_MYDOCS . 'images/logo.png')) {
                $this->Image(FS_MYDOCS . 'images/logo.png', $this->fdf_Xmarcaagua, $this->fdf_Ymarcaagua, 160);
            } else if (file_exists(FS_MYDOCS . 'images/logo.jpg')) {
                $this->Image(FS_MYDOCS . 'images/logo.jpg', $this->fdf_Xmarcaagua, $this->fdf_Ymarcaagua, 160);
            }

            // restore full opacity
            $this->SetAlpha(1);
            $this->Ln(0);
        }

        // Tipo de Documento y Numero
        $this->fact_dev($this->fdf_tipodocumento, $this->fdf_codigo);

        // Fecha factura y Codigo Cliente
        $this->addDate($this->fdf_fecha);
        $this->addClient($this->fdf_codcliente);
        $this->addPageNumber($this->PageNo() . '/{nb}');

        // Datos del Cliente
        $nombrecliente = $this->fdf_nombrecliente . "\n";
        $cliente = $this->fdf_FS_CIFNIF . ": ";
        $cliente .= $this->fdf_cifnif . "\n";
        $cliente .= $this->fdf_direccion . "\n";

        if ($this->fdf_codpostal) {
            $cliente .= $this->fdf_codpostal . " - ";
        }

        $cliente .= $this->fdf_ciudad;

        if ($this->fdf_provincia) {
            $cliente .= " (" . $this->fdf_provincia . ")";
        }
        $cliente .= "\n";

        if ($this->fdc_telefono1 OR $this->fdc_telefono2) {
            $cliente .= ucfirst($this->idioma->fix_html($this->idioma->telefono)) . ": ";
            if ($this->fdc_telefono1) {
                $cliente .= $this->fdc_telefono1;
            }

            if ($this->fdc_telefono2) {
                if ($this->fdc_telefono1) {
                    $cliente .= ' - ';
                }
                $cliente .= $this->fdc_telefono2;
            }

            $cliente .= "\n";
        }

        if ($this->fdc_fax) {
            $cliente .= utf8_decode($this->idioma->fax) . ": " . $this->fdc_fax . "\n";
        }
        if ($this->fdc_email) {
            $cliente .= utf8_decode(ucfirst($this->idioma->fix_html($this->idioma->email))) . ": " . $this->fdc_email . "\n";
        }
        if ($this->fdc_orden) {
            $cliente .= utf8_decode(ucfirst($this->idioma->pedido)) . ": " . $this->fdc_orden . "\n";
        }

        $this->addClientAdresse(utf8_decode($nombrecliente), utf8_decode($cliente));

        // Forma de Pago de la Factura
        if ($this->es_factura) {
            $this->addPago($this->fdf_epago);
        } else
            $this->addExtras($this->fdf_contacto);

        // Divisa de la Factura
        //$this->addDivisa(utf8_decode($this->fdf_divisa));
        // Pais de la Factura
        //$this->addPais(utf8_decode($this->fdf_pais));
        // Pie de la Factura
        //$this->SetFont('Arial','',5);
        //$this->RotatedText(6, 210, utf8_decode($this->fde_piefactura), 90);
        $this->SetFont('Arial', '', 7);
        $this->SetY(-8);
        $this->SetLineWidth(0.1);
        $this->SetTextColor(0);
        if ($this->es_factura) {
            $this->MultiCell(0, 3, utf8_decode($this->fde_piefactura), 0, "J");
        }

        // Cabecera Titulos Columnas
        $this->SetXY(10, 95);
        $this->SetFont("Arial", "B", 9);

        $imprime_albaran = $this->impresion['f_detallada_imprime_albaran'] && !$this->impresion['f_detallada_agrupa_albaranes'];
        for ($i = 0; $i < count($this->datoscab); $i++) {
            if ($imprime_albaran) {
                $this->Cell($this->widths[$i], 5, $this->datoscab[$i], 1, 0, 'C', 1);
            } else {
                if ($i == 0) {
                    $ancho = $this->widths[$i] + $this->widths[$i + 1];
                    $this->Cell($ancho, 5, $this->datoscab[$i + 1], 1, 0, 'C', 1);
                    $i++;
                } else {
                    $this->Cell($this->widths[$i], 5, $this->datoscab[$i], 1, 0, 'C', 1);
                }
            }
        }

        // Cuerpo de la Factura
        $this->Ln();
        $aquiY = $this->GetY() + 0.6;
        $this->SetY($aquiY);
        $aquiX = $this->GetX();

        if ($this->hoja_actual != $this->hoja_nueva) {
            $this->Footer();
        }
    }

    //Pie de pagina
    function Footer()
    {
        //Posicion: a 3 cm del final
        $this->SetY(-30);
        $this->SetLineWidth(0.1);
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 8);
        if ($this->piepagina == true) {
            // Si existen Incluimos las Observaciones
            if ($this->fdf_observaciones != '' && !$this->observaciones_nueva_hoja) {
                $this->addObservaciones(utf8_decode($this->fdf_observaciones));
            }

            if ($this->es_factura) {
                // Lineas de Impuestos
                $this->addLineasIva($this->fdf_lineasiva);

                // Total factura
                $this->addTotal();
            } else {
                // Total albaran
                $this->addTotal();
            }

            if ($this->observaciones_nueva_hoja) {
                $this->hoja_actual = $this->page;
                $this->hoja_nueva = $this->page + 1;
                $this->observaciones_nueva_hoja = false;
                $this->numero_lineas = 0;
                $this->AddPage();
            }
        } else {
            // Neto por Pagina
            $imprime_albaran = $this->impresion['f_detallada_imprime_albaran'] && !$this->impresion['f_detallada_agrupa_albaranes'];
            $this->addNeto();
            $this->DibujaCuadro(count($this->datoscab), 155, $imprime_albaran);
        }
    }

    function Row($data, $ultimo = '1', $haz_linea, $mostrar_cantidad, $mostrar_precio)
    {
        $this->SetFont('Arial', '', 8);

        // Guardamos la posicion Actual
        $x = $this->GetX();
        $y = $this->GetY();

        if ($this->es_factura) {
            $agrupa_albaranes = $this->impresion['f_detallada_agrupa_albaranes'];
            $imprime_albaran = $this->impresion['f_detallada_imprime_albaran'] && !$this->impresion['f_detallada_agrupa_albaranes'];
            $imprime_IVA = true;
        } else {
            $agrupa_albaranes = false;
            $imprime_albaran = false;
            $imprime_IVA = true;
        }
        if ($agrupa_albaranes && ($this->albaran_anterior != $data[0])) {
            $y += 5;
            if ($y > 250) { // Pagina completa
                $this->numero_lineas = $this->lineaactual + $nb;
                $this->AddPage($this->CurOrientation);
                $this->lineaactual = 0;
                $y = 105.6;
            }
        }
        // Imprimimos solo los campos numericos
        for ($i = 0; $i < count($data); $i++) {
            // La descripcion del articulo la trataremos la ultima. Aqui no.
            if ($i != $ultimo) {
                $w = $this->widths[$i];
                if ($i == ($ultimo - 1)) {
                    $x1 = $x + $w;
                    $x += $this->widths[$ultimo] + $w;
                } else {
                    $x += $w;
                }
                // Seleccionar Alineacion
                $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
                // Seleccionar color
                $this->SetTextColor(0);
                if (isset($this->colores[$i][0])) {
                    $this->SetTextColor($this->colores[$i][0], $this->colores[$i][1], $this->colores[$i][2]);
                }
                if (!(!$imprime_albaran && ($i == 0))) {
                    // Escribimos el texto
                    if ($i <= 2) {
                        if ($mostrar_cantidad) {
                            $this->MultiCell($w, 5, $data[$i], 0, $a);
                        }
                    } else {
                        if ($mostrar_precio) {
                            $this->MultiCell($w, 5, $data[$i], 0, $a);
                        }
                    }
                }
                // Fijamos la posicion a la derecha de la celda
                $this->SetXY($x, $y);
            }
        }

        if ($agrupa_albaranes && ($this->albaran_anterior != $data[0]))
            $y -= 5;

        // Por último scribimos La descripcion del articulo
        $this->SetTextColor($this->colores[1][0], $this->colores[1][1], $this->colores[1][2]);
        $w = $this->widths[$ultimo];
        if (!$imprime_albaran) {
            $x1 = 10;
            $inicio = 1;
            $w += $this->widths[0];
        } else {
            $inicio = 0;
        }
        $this->SetXY($x1, $y);

        $a = isset($this->aligns[$ultimo]) ? $this->aligns[$ultimo] : 'L';
        $suma_linea = 0;
        if ($mostrar_cantidad && $mostrar_precio) {
            if ($agrupa_albaranes && ($this->albaran_anterior != $data[0])) {
                $this->SetFont('Arial', 'B', 8);
                $this->MultiCell($w, 5, $data[0], 0, 'C');
                $this->SetFont('Arial', '', 8);
                $this->albaran_anterior = $data[0];
                $suma_linea = 1;
            }
            $this->MultiCell($w, 5, $data[$ultimo], 0, $a);
        } else {
            if ($agrupa_albaranes && ($this->albaran_anterior != $data[0])) {
                $this->SetFont('Arial', 'B', 8);
                $this->MultiCell($w, 5, $data[0], 0, 'C');
                $this->SetFont('Arial', '', 8);
                $this->albaran_anterior = $data[0];
                $suma_linea = 1;
            }
            if ($this->impresion['f_detallada_maquetar_negrita']) {
                $this->SetFont('Arial', 'B', 8);
                $this->MultiCell($w, 5, mb_strtoupper($data[$ultimo], 'utf-8'), 0, $a);
            } else {
                $this->SetFont('Arial', '', 8);
                $this->MultiCell($w, 5, $data[$ultimo], 0, $a);
            }
            $this->SetFont('Arial', '', 8);
        }

        // Calcular la altura MAXIMA de la fila e ir a la siguiente línea
        $nb = 0;
        for ($i = $inicio; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $nb += $suma_linea;

        if (($this->lineaactual + $nb) > 31) { // Mas de una Pagina
            $nbp = intval(($this->lineaactual + $nb) / 31);
            $this->lineaactual = ($this->lineaactual + $nb) - ($nbp * 31);
        } else {
            if (($this->lineaactual + $nb) == 31) { // Pagina completa
                $this->DibujaCuadro(count($this->datoscab), 155, $imprime_albaran);
                $this->numero_lineas = $this->lineaactual + $nb;
                $this->AddPage($this->CurOrientation);
                $this->lineaactual = 1;
            } else {
                $this->lineaactual = $this->lineaactual + $nb; // Una sola Pagina
            }
        }

        $h = 5 * $this->lineaactual;
        $this->Ln($h);
        $this->SetY(100 + $h); // Y=100 en base a la altura de la cabecera
        // Dibujamos una Linea Gris para separar los Articulos
        $aquiX = $this->GetX() + 0.155;
        $aquiY = $this->GetY();
        $this->SetDrawColor(200, 200, 200);
        if ($haz_linea) {
            for ($i = 0; $i < count($this->datoscab); $i++) {
                $finX = $this->widths[$i] + $aquiX - 0.316;
                $this->Line($aquiX, $aquiY, $finX, $aquiY);
                $aquiX = $finX + 0.316;
            }
        } else {
            $this->numero_lineas = $this->lineaactual;
            $this->DibujaCuadro(count($this->datoscab), $this->lineaactual * 5, $imprime_albaran);
        }
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0);
    }

    function DibujaCuadro($columnas, $total_largo, $imprime_albaran)
    {
        $this->SetDrawColor(210, 210, 210);
        $aquiY = 100.6;
        $aquiX = 10.00125;
        for ($i = 0; $i < $columnas; $i++) {
            if (!$imprime_albaran && $i == 0) {
                $ancho = $this->widths[0] + $this->widths[1];
                $this->RoundedRect($aquiX, $aquiY, $ancho, $total_largo, 1, 'D');
                $aquiX += $ancho;
                $i++;
            } else {
                $this->RoundedRect($aquiX, $aquiY, $this->widths[$i], $total_largo, 1, 'D');
                $aquiX += $this->widths[$i];
            }
        }
        $this->SetDrawColor(0, 0, 0);
    }

    function NbLines($w, $txt)
    {
        //Computes the number of lines a MultiCell of width w will take
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else
                $i++;
        }
        return $nl;
    }

    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F') {
            $op = 'f';
        } else if ($style == 'FD' or $style == 'DF') {
            $op = 'B';
        } else
            $op = 'S';

        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2f %.2f m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2f %.2f l', $xc * $k, ($hp - $y) * $k));

        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2f %.2f l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2f %.2f l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2f %.2f l', ($x) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c ', $x1 * $this->k, ($h - $y1) * $this->k, $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
    }

    // Uso:
    // set alpha to semi-transparency
    // $pdf->SetAlpha(0.5, 'Lighten');
    // draw jpeg image
    // $pdf->Image('imagen.jpg',30,30,40);
    // restore full opacity
    // $pdf->SetAlpha(1);
    //	
    // class AlphaPDF
    // alpha: real value from 0 (transparent) to 1 (opaque)
    // bm:    blend mode, one of the following:
    //          Normal, Multiply, Screen, Overlay, Darken, Lighten, ColorDodge, ColorBurn,
    //          HardLight, SoftLight, Difference, Exclusion, Hue, Saturation, Color, Luminosity
    function SetAlpha($alpha, $bm = 'Normal')
    {
        // set alpha for stroking (CA) and non-stroking (ca) operations
        $gs = $this->AddExtGState(array('ca' => $alpha, 'CA' => $alpha, 'BM' => '/' . $bm));
        $this->SetExtGState($gs);
    }

    function AddExtGState($parms)
    {
        $n = count($this->extgstates) + 1;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }

    function SetExtGState($gs)
    {
        $this->_out(sprintf('/GS%d gs', $gs));
    }

    function _enddoc()
    {
        if (!empty($this->extgstates) && $this->PDFVersion < '1.4')
            $this->PDFVersion = '1.4';
        parent::_enddoc();
    }

    function _putextgstates()
    {
        for ($i = 1; $i <= count($this->extgstates); $i++) {
            $this->_newobj();
            $this->extgstates[$i]['n'] = $this->n;
            $this->_out('<</Type /ExtGState');
            $parms = $this->extgstates[$i]['parms'];
            $this->_out(sprintf('/ca %.3F', $parms['ca']));
            $this->_out(sprintf('/CA %.3F', $parms['CA']));
            $this->_out('/BM ' . $parms['BM']);
            $this->_out('>>');
            $this->_out('endobj');
        }
    }

    function _putresourcedict()
    {
        parent::_putresourcedict();
        $this->_out('/ExtGState <<');
        foreach ($this->extgstates as $k => $extgstate) {
            $this->_out('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
        }
        $this->_out('>>');
    }

    function _putresources()
    {
        $this->_putextgstates();
        parent::_putresources();
    }

    // END-class AlphaPDF
    // Girar Texto o Imagen
    function RotatedText($x, $y, $txt, $angle)
    {
        //Text rotated around its origin
        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }

    function RotatedImage($file, $x, $y, $w, $h, $angle)
    {
        //Image rotated around its upper-left corner
        $this->Rotate($angle, $x, $y);
        $this->Image($file, $x, $y, $w, $h);
        $this->Rotate(0);
    }

    function fs_orig_honest()
    {
        $fname = 'view/login/default.html';
        foreach ($GLOBALS['plugins'] as $plugin) {
            if (file_exists('plugins/' . $plugin . '/view/login/default.html')) {
                $fname = 'plugins/' . $plugin . '/view/login/default.html';
                break;
            }
        }

        $txt = file_get_contents($fname);
        if ($txt) {
            if (stripos($txt, 'facturascripts') === FALSE) {
                return FALSE;
            } else {
                return TRUE;
            }
        } else
            return FALSE;
    }

    function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }

        if ($y == -1) {
            $y = $this->y;
        }

        if ($this->angle != 0) {
            $this->_out('Q');
        }

        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    // END - Girar Texto o Imagen
    // Factura / albaran
    function sizeOfText($texte, $largeur)
    {
        $index = 0;
        $nb_lines = 0;
        $loop = TRUE;
        while ($loop) {
            $pos = strpos($texte, "\n");
            if (!$pos) {
                $loop = FALSE;
                $ligne = $texte;
            } else {
                $ligne = substr($texte, $index, $pos);
                $texte = substr($texte, $pos + 1);
            }
            $length = floor($this->GetStringWidth($ligne));
            $res = 1 + floor($length / $largeur);
            $nb_lines += $res;
        }
        return $nb_lines;
    }

    // Empresa
    function addSociete($nom, $adresse, $email, $web)
    {
        $x1 = 10;
        $y1 = 8;
        $this->SetXY($x1, $y1);
        // Nombre empresa > 43 caracteres reducimos tamaño de letra > 57, recort.
        if (strlen($nom) > 43) {
            $this->SetFont('Arial', 'B', 9);
            if (strlen($nom) > 56) {
                $nom = substr($nom, 0, 54) . "...";
            }
        } else
            $this->SetFont('Arial', 'B', 12);

        $this->SetTextColor(0);
        $length = $this->GetStringWidth($nom);
        $this->Cell($length, 4, $nom);
        $this->SetXY($x1, $y1 + 4);
        $this->SetFont('Arial', '', 10);
        $length = $this->GetStringWidth($adresse);
        $this->MultiCell($length, 4, $adresse);

        if ($email != '') {
            $this->SetXY($x1, $y1 + 73);
            $this->SetFont('Arial', '', 9);
            $this->Write(5, utf8_decode(ucfirst($this->idioma->fix_html($this->idioma->email))) . ': ');
            $this->SetTextColor(0, 0, 255);
            $this->Write(5, $email, 'mailto:' . $email);
            $this->SetTextColor(0);
            $this->SetFont('');
        }

        if ($web != '') {
            $this->SetXY($x1, $y1 + 77);
            $this->SetFont('Arial', '', 9);
            $this->Write(5, utf8_decode(mb_strtoupper($this->idioma->web)) . ': ');
            $this->SetTextColor(0, 0, 255);
            $this->Write(5, $web, $web);
            $this->SetTextColor(0);
            $this->SetFont('');
        }
    }

    // Nombre y numero de la factura
    function fact_dev($tipo, $num)
    {
        $r1 = $this->w - 100;
        $r2 = $r1 + 90;
        $y1 = 6;
        $y2 = $y1 + 2;
        $mid = ($r1 + $r2 ) / 2;

        if ($tipo == 'FACTURA') {
            $texto = utf8_decode(mb_strtoupper($this->idioma->factura)) . ' N' . chr(176) . ': ' . $num;
        } else if ($tipo == 'ALBARAN') {
            $texto = utf8_decode(mb_strtoupper($this->idioma->albaran)) . ' N' . chr(176) . ': ' . $num;
        } else {
            $texto = $tipo . ' N' . chr(176) . ': ' . $num;
        }

        $szfont = 15;
        $loop = 0;
        while ($loop == 0) {
            $this->SetFont("Arial", "B", $szfont);
            $sz = $this->GetStringWidth($texto);
            if (($r1 + $sz) > $r2) {
                $szfont --;
            } else
                $loop ++;
        }

        $this->SetLineWidth(0.1);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), $y2, 2.5, 'DF');
        $this->SetXY($r1 + 1, $y1 + 2);
        $this->Cell($r2 - $r1 - 1, 5, $texto, 0, 0, "C");
    }

    function addDate($date)
    {
        $r1 = $this->w - 80;
        $r2 = $r1 + 30;
        $y1 = 15;
        $y2 = $y1;
        $mid = $y1 + ($y2 / 2);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), $y2, 3.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 3);
        $this->SetFont("Arial", "B", 10);
        $this->Cell(10, 3, utf8_decode(mb_strtoupper($this->idioma->fecha)), 0, 0, "C");
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 9);
        $this->SetFont("Arial", "", 9);
        $this->Cell(10, 5, $date, 0, 0, "C");
    }

    function addClient($ref)
    {
        $r1 = $this->w - 50;
        $r2 = $r1 + 40;
        $y1 = 15;
        $y2 = $y1;
        $mid = $y1 + ($y2 / 2);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), $y2, 3.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 3);
        $this->SetFont("Arial", "B", 10);
        $this->Cell(10, 3, utf8_decode(mb_strtoupper($this->idioma->num_cliente)), 0, 0, "C");
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 9);
        $this->SetFont("Arial", "", 9);
        $this->Cell(10, 5, $ref, 0, 0, "C");
    }

    function addPageNumber($page)
    {
        $r1 = $this->w - 100;
        $r2 = $r1 + 20;
        $y1 = 15;
        $y2 = $y1;
        $mid = $y1 + ($y2 / 2);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), $y2, 3.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 3);
        $this->SetFont("Arial", "B", 10);
        $this->Cell(10, 3, utf8_decode(mb_strtoupper($this->idioma->pagina)), 0, 0, "C");
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 3, $y1 + 9);
        $this->SetFont("Arial", "", 9);
        $this->Cell(10, 5, $page, 0, 0, "C");
    }

    /**
     * addClientAdresse: Crea y rellena el cuadro correspondiente a los datos
     *                   del cliente.
     * @param type $adresse
     */
    function addClientAdresse($empresa, $adresse)
    {
        $this->RoundedRect(110, 32, 90, 36, 3.5, 'D');
        $this->Line(110, 38, 200, 38);
        $r1 = $this->w - 97;
        $this->SetFont("Arial", "B", 10);
        $this->Cell(55, 23, utf8_decode(mb_strtoupper($this->idioma->cliente)), 0, 0, "C");
        $y1 = 38;
        $this->SetXY($r1 - 2, $y1);
        $this->SetFont('Arial', 'B', 10);
        $this->Write(5, $empresa);
        $this->SetXY($r1, 43);
        $this->AddFont('Verdana');
        $this->SetFont('Verdana', '', 8);
        $this->MultiCell(87, 3.5, $adresse);
    }

    // Forma de Pago
    function addPago($mode)
    {
        $numlineas = count($mode);
        // default
        $r1 = 110;
        $r2 = $r1 + 90;
        $y1 = 70;
        $y2 = $y1 + 23;
        $mid = $y1 + (($y2 - $y1) / 2);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 2.5, 'D');
        $this->Line($r1, $y1 + 5, $r2, $y1 + 5);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 1);
        $this->SetFont("Arial", "B", 9);
        $this->Cell(10, 4, utf8_decode(mb_strtoupper($this->idioma->forma_pago)), 0, 0, "C");
        $salto = 4;
        $just = 'L';
        if ($numlineas < 3) {
            $this->SetFont("Arial", "", 9);
            $this->SetXY($r1 + 3, $y1 + 6);
            $just = 'C';
        } else {
            $this->SetFont("Arial", "", 8);
            $this->SetXY($r1 + 3, $y1 + 6);
        }

        foreach ($mode as $lin) {
            $this->Cell($r2 - $r1 - 6, $salto, utf8_decode($lin), 0, 2, $just);
        }
    }

    /**
     * addExtras: Añadimos los datos extra del albarán
     * @param type $mode
     */
    function addExtras($mode)
    {
        // default
        $r1 = 110;
        $r2 = $r1 + 90;
        $y1 = 70;
        $y2 = $y1 + 23;
        $mid = $y1 + (($y2 - $y1) / 2);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 2.5, 'D');
        $this->Line($r1, $y1 + 5, $r2, $y1 + 5);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 1);
        $this->SetFont("Arial", "B", 9);
        $this->Cell(10, 4, utf8_decode(mb_strtoupper($this->idioma->albaran)), 0, 0, "C");
        $salto = 4;
        $just = 'L';
        $this->SetFont("Arial", "", 8);
        $this->SetXY($r1 + 3, $y1 + 6);

        foreach ($mode as $lin) {
            $this->Cell($r2 - $r1 - 6, $salto, utf8_decode($lin), 0, 2, $just);
        }
    }

    // Divisa
    function addDivisa($divisa)
    {
        $r1 = 140;
        $r2 = $r1 + 30;
        $y1 = 80;
        $y2 = $y1 + 10;
        $mid = $y1 + (($y2 - $y1) / 2);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 2.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 1);
        $this->SetFont("Arial", "B", 9);
        $this->Cell(10, 4, "DIVISA", 0, 0, "C");
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 5);
        $this->SetFont("Arial", "", 9);
        $this->Cell(10, 5, $divisa, 0, 0, "C");
    }

    // Pais
    function addPais($pais)
    {
        $r1 = 170;
        $r2 = $r1 + 30;
        $y1 = 80;
        $y2 = $y1 + 10;
        $mid = $y1 + (($y2 - $y1) / 2);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 2.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 1);
        $this->SetFont("Arial", "B", 9);
        $this->Cell(10, 4, "PAIS", '', '', "C");
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 5);
        $this->SetFont("Arial", "", 9);
        $this->Cell(10, 5, $pais, '', '', "C");
    }

    // Incluir Observaciones	
    function addObservaciones($observa)
    {
        $altura = $this->GetMultiCellHeight($this->w - 20, 4, $observa) + 14;
        if ($altura > 162) {
            while ($altura > 162) {
                $lastEnterPosition = strrpos($observa, PHP_EOL);
                $observa = mb_substr($observa, 0, $lastEnterPosition);
                $altura = $this->GetMultiCellHeight($this->w - 20, 4, $observa) + 14;
            }
        }
        $posicionX = 112 + ($this->numero_lineas * 5);
        $total_uso = $posicionX + $altura;
        if ($total_uso > 275 && !$this->observaciones_nueva_hoja) {
            $this->observaciones_nueva_hoja = true;
        } else {
            if (($this->hoja_nueva > 0 && $this->hoja_nueva == $this->page) || ($this->hoja_nueva == 0)) {
                $this->SetFont("Arial", "B", 8);
                $this->Text(10, 109 + ($this->numero_lineas * 5), utf8_decode(ucfirst($this->idioma->observaciones)) . ": ");
                $this->SetFont("Arial", "I", 8);
                $this->Line(10, 110 + ($this->numero_lineas * 5), 200, 110 + ($this->numero_lineas * 5));
                $this->SetXY(10, 112 + ($this->numero_lineas * 5));
                $this->MultiCell($this->w - 20, 4, $observa);
            }
        }
    }

    function GetMultiCellHeight($w, $h, $txt, $border = null, $align = 'J')
    {
        // Calculate MultiCell with automatic or explicit line breaks height
        // $border is un-used, but I kept it in the parameters to keep the call
        //   to this function consistent with MultiCell()
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }

        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $height = 0;
        while ($i < $nb) {
            // Get next character
            $c = $s[$i];
            if ($c == "\n") {
                // Explicit line break
                if ($this->ws > 0) {
                    $this->ws = 0;
                    $this->_out('0 Tw');
                }
                //Increase Height
                $height += $h;
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }

                    if ($this->ws > 0) {
                        $this->ws = 0;
                        $this->_out('0 Tw');
                    }
                    //Increase Height
                    $height += $h;
                } else {
                    if ($align == 'J') {
                        $this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->FontSize / ($ns - 1) : 0;
                        $this->_out(sprintf('%.3F Tw', $this->ws * $this->k));
                    }
                    //Increase Height
                    $height += $h;
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
            } else
                $i++;
        }
        // Last chunk
        if ($this->ws > 0) {
            $this->ws = 0;
            $this->_out('0 Tw');
        }
        //Increase Height
        $height += $h;

        return $height;
    }

    // Incluir Lineas de Iva
    function addLineasIva($datos)
    {
        $r1 = 10;
        $y1 = $this->h - 30;

        if ($datos) {
            if (count($datos) > 4) {
                // Comentar o eliminar las siguientes 5 lineas para NO mostrar el error.
                $this->SetFont("Arial", "B", 10);
                $this->SetXY($r1, $y1 + 8);
                $this->Cell(8, 4, "ERROR: Localizadas " . count($datos) . " lineas de " . FS_IVA . "... ", 0, '', "L");
                $this->SetXY($r1, $y1 + 12);
                $this->Cell(8, 4, chr(161) . chr(161) . chr(161) . " Esta plantilla SOLO puede detallar CUATRO lineas de " . FS_IVA . " !!!", 0, '', "L");
            } else {
                for ($i = 1; $i <= count($datos); $i++) {
                    if ($i == 1) {
                        $y2 = $y1 + 6;
                    }
                    if ($i == 2) {
                        $y2 = $y1 + 9;
                    }
                    if ($i == 3) {
                        $y2 = $y1 + 12;
                    }
                    if ($i == 4) {
                        $y2 = $y1 + 15;
                    }
                    $this->SetFont("Arial", "B", 6);
                    $this->SetXY($r1, $y2);
                    $this->Cell(8, 4, $datos[$i][0], 0, '', "L");
                    $this->Cell(18, 4, $datos[$i][1], 0, '', "R");
                    $this->Cell(7, 4, $datos[$i][2], 0, '', "R");
                    $this->Cell(18, 4, $datos[$i][3], 0, '', "R");
                    $this->Cell(7, 4, $datos[$i][4], 0, '', "R");
                    $this->Cell(18, 4, $datos[$i][5], 0, '', "R");
                    $this->Cell(7, 4, $datos[$i][6], 0, '', "R");
                    $this->Cell(18, 4, $datos[$i][7], 0, '', "R");
                    $this->SetFont("Arial", "B", 6.5);
                    $this->Cell(24, 4, $datos[$i][8], 0, '', "R");
                }
            }
        }
    }

    function addNeto()
    {
        $r1 = $this->w - 70;
        $r2 = $r1 + 60;
        $y1 = $this->h - 30;
        $y2 = $y1 + 20;
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 1.5, 'D');
        $this->Line($r1 + 15, $y1, $r1 + 15, $y2);
        $this->Line($r1 + 15, $y1 + 4, $r2, $y1 + 4);
        $this->SetFont("Arial", "B", 8);
        $this->SetXY($r1 + 22, $y1);
        $this->Cell(30, 4, $this->fdf_divisa, 0, 0, "C");
        $this->SetFont("Arial", "B", 8);
        $this->SetXY($r1, $y1 + 7);
        $this->Cell(15, 4, utf8_decode(mb_strtoupper($this->idioma->neto)), 0, 0, "C");

        // Total Neto de la pagina
        $this->SetFont("Arial", "", 9);
        $this->SetXY($r1 + 16, $y1 + 6.5);
        if (isset($this->neto)) {
            $this->Cell(43, 4, $this->neto, 0, 0, "C");
        }

        // Suma y Sigue		
        $this->SetFont("Arial", "B", 6);
        $this->SetXY($r1 + 16, $y1 + 13);
        $this->MultiCell(43, 3, '(' . utf8_decode(mb_strtoupper($this->idioma->suma_sigue)) . ')', 0, 'C');
    }

    function addTotal()
    {
        if ($this->es_factura) {
            $this->SetFont("Arial", "B", 8);
            $r1 = 10;
            $r2 = $r1 + 125;
            $y1 = $this->h - 30;
            $y2 = $y1 + 20;
            $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 1.5, 'D');
            $this->Line($r1, $y1 + 4, $r2, $y1 + 4);
            $this->Line($r1 + 8, $y1 + 4, $r1 + 8, $y2);
            $this->Line($r1 + 26, $y1, $r1 + 26, $y2);
            $this->Line($r1 + 33, $y1 + 4, $r1 + 33, $y2);
            $this->Line($r1 + 51, $y1, $r1 + 51, $y2);
            $this->Line($r1 + 58, $y1 + 4, $r1 + 58, $y2);
            $this->Line($r1 + 76, $y1, $r1 + 76, $y2);
            $this->Line($r1 + 83, $y1 + 4, $r1 + 83, $y2);
            $this->Line($r1 + 101, $y1, $r1 + 101, $y2);
            $this->SetXY($r1, $y1);
            $this->Cell(26, 4, utf8_decode(mb_strtoupper($this->idioma->neto)), 0, '', "C");
            $this->SetX($r1 + 26);
            $this->Cell(25, 4, utf8_decode(mb_strtoupper($this->idioma->iva)), 0, '', "C");
            $this->SetX($r1 + 51);
            $this->Cell(25, 4, utf8_decode(mb_strtoupper($this->idioma->rec_equiv)), 0, '', "C");
            $this->SetX($r1 + 76);
            $this->Cell(25, 4, utf8_decode(mb_strtoupper($this->idioma->irpf)), 0, '', "C");
            $this->SetX($r1 + 101);
            $this->Cell(24, 4, utf8_decode(mb_strtoupper($this->idioma->importes)), 0, '', "C");
        }

        $r1 = $this->w - 70;
        $r2 = $r1 + 60;
        $y1 = $this->h - 30;
        $y2 = $y1 + 20;
        $this->SetLineWidth(0.15);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 1.5, 'D');
        $this->Line($r1 + 15, $y1, $r1 + 15, $y2);
        $this->Line($r1 + 15, $y1 + 4, $r2, $y1 + 4);
        $this->SetFont("Arial", "B", 8);
        $this->SetXY($r1 + 22, $y1);
        if ($this->es_factura) {
            $this->Cell(30, 4, $this->fdf_divisa, 0, 0, "C");
        } else
            $this->Cell(30, 4, $this->fdf_divisa . ' ' . utf8_decode($this->idioma->neto), 0, 0, "C");
        $this->SetFont("Arial", "B", 8);
        $this->SetXY($r1, $y1 + 7);
        $this->Cell(15, 4, "TOTAL", 0, 0, "C");
        $this->SetLineWidth(0.1);

        // Total factura
        $this->SetFont("Arial", "B", 9);
        $this->SetXY($r1 + 16, $y1 + 6.5);
        $this->Cell(43, 4, $this->fdf_numtotal, 0, 0, "C");

        // Total factura en texto		
        $this->SetFont("Arial", "B", 6);
        $this->SetXY($r1 + 16, $y1 + 13);
        $texto = $this->numtoletras($this->fdf_textotal);
        $this->MultiCell(43, 2, $texto, 0, 'C');
    }

    //------    CONVERTIR NUMEROS A LETRAS         ---------------
    //------    Máxima cifra soportada: 18 dígitos con 2 decimales
    //------    999,999,999,999,999,999.99
    // NOVECIENTOS NOVENTA Y NUEVE MIL NOVECIENTOS NOVENTA Y NUEVE con 99/100
    function numtoletras($xcifra, $convert00decimal = true)
    {
        $xarray = array(0 => "Cero",
            1 => "UN", "DOS", "TRES", "CUATRO", "CINCO", "SEIS", "SIETE", "OCHO", "NUEVE",
            "DIEZ", "ONCE", "DOCE", "TRECE", "CATORCE", "QUINCE", "DIECISEIS", "DIECISIETE", "DIECIOCHO", "DIECINUEVE",
            "VEINTI", 30 => "TREINTA", 40 => "CUARENTA", 50 => "CINCUENTA", 60 => "SESENTA", 70 => "SETENTA", 80 => "OCHENTA", 90 => "NOVENTA",
            100 => "CIENTO", 200 => "DOSCIENTOS", 300 => "TRESCIENTOS", 400 => "CUATROCIENTOS", 500 => "QUINIENTOS", 600 => "SEISCIENTOS", 700 => "SETECIENTOS", 800 => "OCHOCIENTOS", 900 => "NOVECIENTOS"
        );
        //
        $xcifra_org = $xcifra;
        $xcifra = trim(abs($xcifra));
        $xlength = strlen($xcifra);
        $xpos_punto = strpos($xcifra, ".");
        $xaux_int = $xcifra;
        if ($convert00decimal) {
            $xdecimales = "00";
        } else
            $xdecimales = "";

        if (!($xpos_punto === false)) {
            if ($xpos_punto == 0) {
                $xcifra = "0" . $xcifra;
                $xpos_punto = strpos($xcifra, ".");
            }
            $xaux_int = substr($xcifra, 0, $xpos_punto); // obtengo el entero de la cifra a convertir
            // obtengo los valores decimales en letras
            $xdecimales = $this->numtoletras(substr($xcifra . "00", $xpos_punto + 1, 2), false);
        }

        $XAUX = str_pad($xaux_int, 18, " ", STR_PAD_LEFT); // ajusto la longitud de la cifra, para que sea divisible por centenas de miles (grupos de 6)
        $xcadena = "";
        for ($xz = 0; $xz < 3; $xz++) {
            $xaux = substr($XAUX, $xz * 6, 6);
            $xi = 0;
            $xlimite = 6; // inicializo el contador de centenas xi y establezco el límite a 6 dígitos en la parte entera
            $xexit = true; // bandera para controlar el ciclo del While
            while ($xexit) {
                if ($xi == $xlimite) { // si ya ha llegado al límite máximo de enteros
                    break; // termina el ciclo
                }

                $x3digitos = ($xlimite - $xi) * -1; // comienzo con los tres primeros digitos de la cifra, comenzando por la izquierda
                $xaux = substr($xaux, $x3digitos, abs($x3digitos)); // obtengo la centena (los tres dígitos)
                for ($xy = 1; $xy < 4; $xy++) { // ciclo para revisar centenas, decenas y unidades, en ese orden
                    switch ($xy) {
                        case 1: // checa las centenas
                            if (substr($xaux, 0, 3) < 100) { // si el grupo de tres dígitos es menor a una centena ( < 99) no hace nada y pasa a revisar las decenas
                            } else {
                                $key = (int) substr($xaux, 0, 3);
                                if (TRUE === array_key_exists($key, $xarray)) {  // busco si la centena es número redondo (100, 200, 300, 400, etc..)
                                    $xseek = $xarray[$key];
                                    $xsub = $this->subfijo($xaux); // devuelve el subfijo correspondiente (Millón, Millones, Mil o nada)
                                    if (substr($xaux, 0, 3) == 100)
                                        $xcadena = " " . $xcadena . " CIEN " . $xsub;
                                    else
                                        $xcadena = " " . $xcadena . " " . $xseek . " " . $xsub;
                                    $xy = 3; // la centena fue redonda, entonces termino el ciclo del for y ya no reviso decenas ni unidades
                                }
                                else { // entra aquí si la centena no es numero redondo (101, 253, 120, 980, etc.)
                                    $key = (int) substr($xaux, 0, 1) * 100;
                                    $xseek = $xarray[$key]; // toma el primer caracter de la centena y lo multiplica por cien y lo busca en el arreglo (para que busque 100,200,300, etc)
                                    $xcadena = " " . $xcadena . " " . $xseek;
                                } // ENDIF ($xseek)
                            } // ENDIF (substr($xaux, 0, 3) < 100)
                            break;

                        case 2: // Chequear las decenas (con la misma lógica que las centenas)
                            if (substr($xaux, 1, 2) < 10) {
                                
                            } else {
                                $key = (int) substr($xaux, 1, 2);
                                if (TRUE === array_key_exists($key, $xarray)) {
                                    $xseek = $xarray[$key];
                                    $xsub = $this->subfijo($xaux);
                                    if (substr($xaux, 1, 2) == 20)
                                        $xcadena = " " . $xcadena . " VEINTE " . $xsub;
                                    else
                                        $xcadena = " " . $xcadena . " " . $xseek . " " . $xsub;
                                    $xy = 3;
                                } else {
                                    $key = (int) substr($xaux, 1, 1) * 10;
                                    $xseek = $xarray[$key];
                                    if (20 == substr($xaux, 1, 1) * 10)
                                        $xcadena = " " . $xcadena . " " . $xseek;
                                    else
                                        $xcadena = " " . $xcadena . " " . $xseek . " Y ";
                                } // ENDIF ($xseek)
                            } // ENDIF (substr($xaux, 1, 2) < 10)
                            break;

                        case 3: // Chequear las unidades
                            if (substr($xaux, 2, 1) < 1) { // si la unidad es cero, ya no hace nada
                            } else {
                                $key = (int) substr($xaux, 2, 1);
                                $xseek = $xarray[$key]; // obtengo directamente el valor de la unidad (del uno al nueve)
                                $xsub = $this->subfijo($xaux);
                                $xcadena = " " . $xcadena . " " . $xseek . " " . $xsub;
                            } // ENDIF (substr($xaux, 2, 1) < 1)
                            break;
                    } // END SWITCH
                } // END FOR
                $xi = $xi + 3;
            } // ENDDO

            if (substr(trim($xcadena), -5, 5) == "ILLON") { // si la cadena obtenida termina en MILLON o BILLON, entonces le agrega al final la conjuncion DE
                $xcadena .= " DE";
            }

            if (substr(trim($xcadena), -7, 7) == "ILLONES") { // si la cadena obtenida en MILLONES o BILLONES, entoncea le agrega al final la conjuncion DE
                $xcadena .= " DE";
            }

            // ----------- esta línea la puedes cambiar de acuerdo a tus necesidades o a tu país -------
            if (trim($xaux) != "") {
                switch ($xz) {
                    case 0:
                        if (trim(substr($XAUX, $xz * 6, 6)) == "1") {
                            $xcadena .= "UN BILLON ";
                        } else
                            $xcadena .= " BILLONES ";
                        break;

                    case 1:
                        if (trim(substr($XAUX, $xz * 6, 6)) == "1") {
                            $xcadena .= "UN MILLON ";
                        } else
                            $xcadena .= " MILLONES ";
                        break;

                    case 2:
                        if ($xdecimales === "00") {
                            $xdecimales = "CERO";
                        }

                        if ($xcifra < 1) {
                            if ($convert00decimal) {
                                $xcadena = "CERO con $xdecimales cent.";
                            } else
                                $xcadena = "CERO";
                        }
                        if ($xcifra >= 1 && $xcifra < 2) {
                            if ($convert00decimal) {
                                $xcadena = "UNO con $xdecimales cent.";
                            } else
                                $xcadena = "UNO";
                        }
                        if ($xcifra >= 2) {
                            if ($convert00decimal) {
                                $xcadena .= " con $xdecimales cent.";
                            }
                        }
                        break;
                } // endswitch ($xz)
            } // ENDIF (trim($xaux) != "")

            $xcadena = str_replace("VEINTI ", "VEINTI", $xcadena); // quito el espacio para el VEINTI, para que quede: VEINTICUATRO, VEINTIUN, VEINTIDOS, etc
            $xcadena = str_replace("  ", " ", $xcadena); // quito espacios dobles
            $xcadena = str_replace("UN UN", "UN", $xcadena); // quito la duplicidad
            $xcadena = str_replace("  ", " ", $xcadena); // quito espacios dobles
            $xcadena = str_replace("BILLON DE MILLONES", "BILLON DE", $xcadena); // corrigo la leyenda
            $xcadena = str_replace("BILLONES DE MILLONES", "BILLONES DE", $xcadena); // corrigo la leyenda
            $xcadena = str_replace("DE UN", "UN", $xcadena); // corrigo la leyenda
        } // ENDFOR ($xz)

        $xcadena = str_replace("UN MIL ", "MIL ", $xcadena); // quito el BUG de UN MIL
        if ($xcifra_org < 0) {
            return trim("(Menos) " . $xcadena);
        } else
            return trim($xcadena);
    }

    // END FUNCTION

    function subfijo($xx)
    { // esta función genera un subfijo para la cifra
        $xx = trim($xx);
        $xstrlen = strlen($xx);
        if ($xstrlen == 1 || $xstrlen == 2 || $xstrlen == 3) {
            $xsub = "";
        }

        //
        if ($xstrlen == 4 || $xstrlen == 5 || $xstrlen == 6) {
            $xsub = "MIL";
        }
        //
        return $xsub;
    }
    // END FUNCTION	
}
