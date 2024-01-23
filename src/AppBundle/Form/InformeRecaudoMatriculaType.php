<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class InformeRecaudoMatriculaType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('matricula',TextType::class,array(
          "label"=>"NIU: *",
          "required"=>true,
          "attr"=>array("class"=>"form-control","placeholder"=>"Matricula")
        ))
        ->add('fechaInicio',DateType::class,array(
          "label"=>"Fecha Desde:",
          "widget"=>"single_text",
          "required"=>false,
          "attr"=>array("class"=>"form-control")
        ))->add('fechaFin',DateType::class,array(
          "label"=>"Fecha Hasta:",
          "widget"=>"single_text",
          "required"=>false,
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
        return 'appbundle_informerecaudomatricula';
    }


}
