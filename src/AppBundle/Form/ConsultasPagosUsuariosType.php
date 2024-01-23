<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConsultasPagosUsuariosType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add("nroFactura",null,array(
          "label" => "Número de Factura:",
          "required" => false,
          "attr" => array("class"=>"form-control")
        ))->add("matricula",null,array(
          "label" => "Matricula (NIU):",
          "required" => false,
          "attr" => array("class"=>"form-control","autofocus"=>true)
        ))->add("codigoTransaccion",null,array(
          "label" => "Código de la Transacción:",
          "required" => false,
          "attr" => array("class"=>"form-control")
        ));
    }/**
     * {@inheritdoc}
     */
    // public function configureOptions(OptionsResolver $resolver)
    // {
    //     $resolver->setDefaults(array(
    //         'data_class' => 'AppBundle\Entity\CierresDeCajas'
    //     ));
    // }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_pagosusuarios';
    }


}
