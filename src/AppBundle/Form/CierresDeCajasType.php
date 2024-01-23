<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class CierresDeCajasType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('totalRecaudoCaja',NumberType::class,array(
          "label"=>"Total Recaudado *",
          "attr"=>array("class"=>"form-control w-50")
        ))
        ->add('vlrEnCaja',NumberType::class,array(
          "label"=>"Valor en Caja *",
          "attr"=>array("class"=>"form-control w-50","placeholder" => 0)
        ))
        ->add('diferenciaCierre',NumberType::class,array(
          "label"=>"Diferencia del Cierre *",
          'required' => false,
          "attr"=>array("class"=>"form-control w-50","disabled"=>false,"placeholder" => 0)
        ))
        ->add('totalColillas',NumberType::class,array(
          "label"=>"Total Colillas",
          'required' => false,
          "attr"=>array("class"=>"form-control w-50","placeholder" => 0)
        ));
    }/**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\CierresDeCajas'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_cierresdecajas';
    }


}
