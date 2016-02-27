<?php

/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2016   César Sáez Rodríguez   NATHOO@lacalidad.es
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


/**
 * Description of opciones_factura_detallada
 *
 * @author César
 */
class opciones_factura_detallada extends fs_controller
{
   public $factura_detallada_setup;
   public $colores;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Opciones', 'Factura Detallada', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->check_menu();
      $this->share_extensions();

      $this->colores = array("gris", "rojo", "verde", "azul","naranja","amarillo","marron", "blanco");
      
      /// cargamos la configuración
      $fsvar = new fs_var();
      $this->factura_detallada_setup = $fsvar->array_get(
         array(
            'f_detallada_color' => 'azul'
         ),
         FALSE
      );
      
      if( isset($_POST['factura_detallada_setup']) )
      {
         $this->factura_detallada_setup['f_detallada_color'] = $_POST['color'];
         
         if( $fsvar->array_save($this->factura_detallada_setup) )
         {
            $this->new_message('Datos guardados correctamente.');
         }
         else
            $this->new_error_msg('Error al guardar los datos.');
      }
   }
   
   private function share_extensions()
   {
   }
   
   private function check_menu()
   {

   }

}
