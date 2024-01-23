<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class InformeCierresDeCajasType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('inicioFechaCierreCaja',DateType::class,array(
          "label"=>"Fecha Inicio del Cierre de Caja *",
          "widget"=>"single_text",
          "attr"=>array("class"=>"form-control")
        ))->add('finFechaCierreCaja',DateType::class,array(
          "label"=>"Fecha Fin del Cierre de Caja *",
          "widget"=>"single_text",
          "attr"=>array("class"=>"form-control")
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
        return 'appbundle_informecierresdecajas';
    }


}
