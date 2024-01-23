<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\HttpFoundation\Session\Session;

use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\DateTypeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;

class NovedadesType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('moduloAfectado',ChoiceType::class,array(
          "label"=>"Módulo ha Afectar: *",
          "choices"=>[
            "Pagos"=>"pagos",
            //"Cierres de Cajas"=>"cierres de cajas"
          ],
          "placeholder"=>"Módulo",
          "attr"=>["class"=>"form-control w-100 m-1","ng-model"=>"vm.modulo"]
        ))->add('nroFactura',NumberType::class,array(
          "label"=>"Número de la Factura: *",
          "attr"=>array("class"=>"form-control w-100 m-1","ng-model"=>"vm.nroFactura","placeholder"=>"Número de la Factura")
        ));
    }
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_novedades';
    }


}
