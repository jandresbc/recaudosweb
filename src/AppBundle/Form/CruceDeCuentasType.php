<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;

class CruceDeCuentasType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('cajas',EntityType::class,array(
          "label"=>"Caja: *",
          "required"=>true,
          "attr"=>array("class"=>"form-control w-75 select2"),
          "placeholder"=>"Seleccione",
          "class"=>"AppBundle:Cajas",
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
            $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();

            if($session->get('rol') != 'Superusuario'){
              $ESA = $em->createQuery("
                SELECT ESA FROM AppBundle:EmpresasSedesAgencias ESA
                WHERE ESA.idSedeAgencia = :idSAgencia
              ")->setParameter("idSAgencia",$session->get('idSedeAgencia'))->getResult();
            }

            $query = $er->createQueryBuilder("C")->where("C.hasArchivado = 0")
            ->andWhere("C.idUsuario = :idUs")
            ->setParameter("idUs",$session->get("idUsuario"));

            if($session->get('rol') != 'Superusuario'){
              foreach ($ESA as $key => $value) {
                $query->andWhere("C.idEmpresaSedeAgencia = :idESA".$key)
                ->setParameter("idESA".$key,$value->getIdEmpresaSedeAgencia()->getIdEmpresaSedeAgencia());
              }
            }

            return $query;
          }
        ))
        ->add('niu',NumberType::class,array(
          "label"=>"NIU:",
          "required"=>false,
          "attr"=>array("class"=>"form-control w-75","placeholder"=>"Digite el NIU a consultar")
        ))
        ->add('nroFactura',NumberType::class,array(
          "label"=>"Nro Factura:",
          "required"=>false,
          "attr"=>array("class"=>"form-control w-75 d-inline-block","placeholder"=>"Digite el Número de Factura a consultar")
        ))
        ->add("metodoPago",ChoiceType::class,[
          "label"=>"Método de Pago: *",
          "attr"=>array("class"=>"form-control w-75"),
          "choices"=>[
            "Otro"=>"otro",
            "Consignacion"=>"Consignación",
            "Cheque"=>"Cheque",
            "PSE"=>"PSE"
          ]
        ])
        ->add('facturas',ChoiceType::class,array(
          "label"=>"Facturas:",
          "required"=>false,
          "placeholder"=>"Seleccione",
          "attr"=>array("class"=>"form-control select2 w-75")
        ))
        ->add('valorCruce',NumberType::class,array(
          "label"=>"Valor Pagado Final por el Convenio y/o Contrato: *",
          'required' => true,
          "attr"=>array("class"=>"form-control w-75")
        ))
        ->add('observaciones',TextareaType::class,array(
          "label"=>"Observaciones / Descripción: *",
          'required' => true,
          "attr"=>array("class"=>"form-control w-75")
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
        return 'appbundle_crucedecuentas';
    }


}
