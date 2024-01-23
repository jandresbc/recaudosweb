<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;

class FacturasType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $session = new Session();
        $builder
        ->add('nroFactura',NumberType::class,array(
          "label"=>"Número Factura: *",
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))
        ->add('matricula',NumberType::class,array(
          "label"=>"Matrícula (NIU): *",
          "attr"=>array("class"=>"form-control w-75 m-1 d-inline-block","placeholder"=>"Digite la matricula")
        ))
        ->add('nombreUsuario',null,array(
          "label"=>"Nombre del Usuario: *",
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))
        ->add('concepto',null,array(
          "label"=>"Concepto: *",
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))
        ->add('valorFactura',null,array(
          "label"=>"Valor Factura: *",
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))
        ->add('fechaVencimiento',DateType::class,array(
          "label"=>"Fecha Vencimiento: *",
          "widget"=>"single_text",
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))
        ->add('observaciones',TextareaType::class,array(
          "label"=>"Observaciones:",
          "required"=>false,
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))
        ->add('mesFacturado',ChoiceType::class,array(
          "label"=>"Mes Facturado: *",
          "data"=>$session->get("mesPeriodoActual"),
          "choices"=>array(
            "Enero"=>1,
            "Febrero"=>2,
            "Marzo"=>3,
            "Abril"=>4,
            "Mayo"=>5,
            "Junio"=>6,
            "Julio"=>7,
            "Agosto"=>8,
            "Septiembre"=>9,
            "Octubre"=>10,
            "Noviembre"=>11,
            "Diciembre"=>12
          ),
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))
        ->add('anioFacturado',null,array(
          "label"=>"Año Facturado: *",
          "data"=>$session->get("anioPeriodoActual"),
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))
        ->add('mesesAtrasados',NumberType::class,array(
          "label"=>"Meses Atrasados:",
          "required"=>false,
          "attr"=>array("class"=>"form-control w-100 m-1","type"=>"number")
        ))
        ->add('periodoActual',ChoiceType::class,array(
          "label"=>"Periodo Actual: *",
          "attr"=>array("class"=>"form-control w-100 m-1"),
          "choices"=>array(
            "Si"=>1,
            "No"=>0
          )
        ))
        ->add('isAbono',ChoiceType::class,array(
          "label"=>"¿Es Abono?: *",
          "attr"=>array("class"=>"form-control w-100 m-1"),
          "choices"=>array(
            "No"=>0,
            "Si"=>1
          )
        ))
        // ->add('isAbono',ChoiceType::class,array(
        //   "label"=>"¿Es Abono?: *",
        //   "attr"=>array("class"=>"form-control w-100 m-1"),
        //   "multiple"=>false,
        //   "expanded"=>true,
        //   "choices"=>array(
        //     "No"=>0,
        //     "Si"=>1
        //   )
        // ))
        ->add('idEmpresa',EntityType::class,array(
          "label"=>"Empresa: *",
          "attr"=>array("class"=>"form-control w-100 m-1 select2"),
          "placeholder"=>"Seleccione",
          "class"=>"AppBundle:Empresas",
          "choice_attr"=>function($id,$key,$value){
            if($key == 0){
              return ["selected"=>"selected"];
            }else{
              return ["value"=>$value];
            }
          },
          "query_builder"=>function(EntityRepository $er){
            $session = new Session();
            $query = null;
            $em = $GLOBALS['kernel']->getContainer()
            ->get('doctrine')->getEntityManager();

            if($session->get('rol') != 'Superusuario'){
              $ESA = $em->createQuery("
                SELECT ESA FROM
                AppBundle:EmpresasSedesAgencias ESA
                WHERE ESA.idSedeAgencia = :idSAgencia
              ")->setParameter("idSAgencia",$session->get('idSedeAgencia'))
              ->getResult();
            }

            $query = $er->createQueryBuilder("E");

            if($session->get('rol') != 'Superusuario'){
              foreach ($ESA as $key => $value) {
                $query->andWhere("E.idEmpresa = :idEmp".$key)
                ->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
              }
            }

            return $query;
          }
        ));
    }/**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Facturas'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_facturas';
    }


}
