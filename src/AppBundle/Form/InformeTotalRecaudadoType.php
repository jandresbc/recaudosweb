<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Session\Session;

class InformeTotalRecaudadoType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('Municipio',EntityType::class,array(
          "label"=>"Municipio:",
          "required"=>false,
          "attr"=>array("class"=>"select2 form-control","placeholder"=>"Seleccione"),
          "class"=>"AppBundle:Divipola",
          "query_builder"=>function(EntityRepository $er){
              $session = new Session();
              $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
              $rol = $session->get("rol");
              $idSA = $session->get("idSedeAgencia");
              
              $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
              ->findBy(["idSedeAgencia"=>$idSA]);

              $query = $er->createQueryBuilder("D")
              ->join("AppBundle:SedesAgencias","SA","WITH","D.divipola = SA.idDivipola")
              ->join("AppBundle:Agencias","A","WITH","A.idAgencia = SA.idAgencia")
              ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","ESA.idSedeAgencia = SA.idSedeAgencia");

              if($rol == 'Administrador Agencias'){
                $query->andWhere("A.idAgencia = :idAgencia")
                ->setParameter("idAgencia",$ESAUsuario[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());
              }else if($rol == 'Administrador' || $rol == 'Auditor'){
                $query->andWhere("ESA.idEmpresa = :idEmpresa")
                ->setParameter("idEmpresa",$ESAUsuario[0]->getIdEmpresa());
              }else if($rol == 'Cajero'){
                $query->andWhere("ESA.idSedeAgencia = :idSA")
                ->setParameter("idSA",$idSA);
              }

              return $query;
          }
        ))
        ->add('Agencias',EntityType::class,array(
          "label"=>"Agencias:",
          "required"=>false,
          "placeholder"=>"Seleccione",
          "attr"=>array("class"=>"select2 form-control","data-placeholder"=>"Seleccione"),
          "class"=>"AppBundle:Agencias",
          'choice_attr' => function($choice, $key, $value) {
            $session = new Session();
            $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
            $ESAUsuario = $em->getRepository("AppBundle:SedesAgencias")
            ->findBy(["idSedeAgencia"=>$session->get('idSedeAgencia')]);

            if($session->get('rol') == 'Administrador Agencias'){
              if($value == $ESAUsuario[0]->getIdAgencia()->getIdAgencia()){
                return ['selected' => 'selected'];
              }else{
                return ['value' => $value];
              }
            }else{
              return ['value' => $value];
            }
          },
          "query_builder"=>function(EntityRepository $er){
              $session = new Session();
              $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
              $rol = $session->get("rol");
              $idSA = $session->get("idSedeAgencia");

              $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
              ->findBy(["idSedeAgencia"=>$idSA]);

              $query = $er->createQueryBuilder("A")
              ->join("AppBundle:SedesAgencias","SA","WITH","A.idAgencia = SA.idAgencia")
              ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","ESA.idSedeAgencia = SA.idSedeAgencia");

              if($rol == 'Administrador Agencias'){
                $query->andWhere("A.idAgencia = :idAgencia")
                ->setParameter("idAgencia",$ESAUsuario[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());
              }else if($rol == 'Administrador' || $rol == 'Auditor'){
                $query->andWhere("ESA.idEmpresa = :idEmpresa")
                ->setParameter("idEmpresa",$ESAUsuario[0]->getIdEmpresa());
              }else if($rol == 'Cajero'){
                $query->andWhere("ESA.idSedeAgencia = :idSA")
                ->setParameter("idSA",$idSA);
              }

              return $query;
          }
        ))
        ->add('sedesAgencias',EntityType::class,array(
          "label"=>"Sede Agencia:",
          "required"=>false,
          "attr"=>array("class"=>"form-control select2","data-placeholder"=>"Seleccione"),
          "placeholder"=>"Seleccione",
          "class"=>"AppBundle:EmpresasSedesAgencias",
          'choice_attr' => function($choice, $key, $value) {
            $session = new Session();

            if($session->get('rol') == 'Cajero'){
              if($value == $session->get('idSedeAgencia')){
                return ['selected' => 'selected'];
              }else{
                return ['value' => $value];
              }
            }else{
              return ['value' => $value];
            }
          },
          "query_builder"=>function(EntityRepository $er){
              $session = new Session();
              $rol = $session->get("rol");
              $em = $GLOBALS['kernel']->getContainer()
              ->get('doctrine')->getEntityManager();
              $idSA = $session->get("idSedeAgencia");

              $AgenciaUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
              ->findBy(
                array("idSedeAgencia"=>$idSA)
              );

              $query = $er->createQueryBuilder("ESA")
              ->join("AppBundle:SedesAgencias","SA","WITH","ESA.idSedeAgencia = SA.idSedeAgencia")
              ->join("AppBundle:Agencias","A","WITH","SA.idAgencia = A.idAgencia");

              if($rol == 'Administrador Agencias'){
                $query->where("A.idAgencia = :idAgencia")
                ->setParameter("idAgencia",$AgenciaUsuario[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());
              }else if($rol == 'Administrador' || $rol == 'Auditor'){
                $query->where("ESA.idEmpresa = :idEmpresa")
                ->setParameter("idEmpresa",$AgenciaUsuario[0]->getIdEmpresa());
              }else if($rol == 'Cajero'){
                $query->where("ESA.idSedeAgencia = :idSA")
                ->setParameter("idSA",$idSA);
              }

              return $query;
          }
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
        return 'appbundle_informetotalreaudado';
    }


}
