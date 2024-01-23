<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Session\Session;

class CajasType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('nombreCaja',null,array(
          "label"=>"Titulo de la Caja *",
          "attr"=>array("class"=>"form-control w-50")
        ))
        ->add('idUsuario',null,array(
          "label"=>"Usuario *",
          "attr"=>array("class"=>"form-control w-50 select2"),
          "class"=>"AppBundle:Usuarios",
          'choice_attr' => function($choice, $key, $value) {
            $session = new Session();

            if($session->get('rol') == 'Cajero'){
              if($value == $session->get('idUsuario')){
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
            $query = null;
            $em = $GLOBALS['kernel']->getContainer()
            ->get('doctrine')->getEntityManager();

            $usu = $em->createQuery("
              SELECT U FROM
              AppBundle:Usuarios U
              WHERE U.idUsuario = :idUs
            ")->setParameter("idUs",$session->get('idUsuario'))
            ->getResult();

            //Validación para Administradores de Agencias
            if($session->get('rol') == 'Administrador Agencias'){
              $query = $er->createQueryBuilder("U")
              ->leftJoin("AppBundle:EmpresasSedesAgencias","ESA","WITH","U.idSedeAgencia=ESA.idSedeAgencia")
              ->leftJoin("AppBundle:SedesAgencias","SA","WITH","ESA.idSedeAgencia=SA.idSedeAgencia")
              ->leftJoin("AppBundle:Agencias","A","WITH","SA.idAgencia=A.idAgencia")
              ->where("SA.idAgencia = :idAgencia")
              ->setParameter("idAgencia",$usu[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());
            }else if($session->get('rol') == 'Cajero'){//Validación para Cajeros.
              $query = $er->createQueryBuilder("U")
              ->where("U.idUsuario = :idUsu")
              ->setParameter("idUsu",$session->get('idUsuario'));
            }

            return $query;
          }
        ))
        ->add('idEmpresaSedeAgencia',EntityType::class,array(
          "label"=>"Sede Agencia *",
          "placeholder"=>"Seleccione",
          "attr"=>array("class"=>"form-control w-50 select2"),
          "class"=>"AppBundle:EmpresasSedesAgencias",
          'choice_attr' => function($choice, $key, $value) {
            $em = $GLOBALS['kernel']->getContainer()
            ->get('doctrine')->getEntityManager();
            $session = new Session();

            $EmpSedAgeUS = $em->getRepository("AppBundle:EmpresasSedesAgencias")
            ->findBy(array("idSedeAgencia"=>$session->get('idSedeAgencia')));

            if($session->get('rol') == 'Cajero'){
              if(count($EmpSedAgeUS) == 1){
                if($value == $EmpSedAgeUS[0]->getIdEmpresaSedeAgencia()){
                  return ['selected' => 'selected'];
                }else{
                  return ['value' => $value];
                }
              }else if(count($EmpSedAgeUS) > 1){
                // foreach ($EmpSedAgeUS as $k => $val) {
                  return ['value' => $value];
                // }
              }
            }else{
              return ['value' => $value];
            }
          },
          "query_builder"=>function(EntityRepository $er){
            $session = new Session();
            $query = null;
            $em = $GLOBALS['kernel']->getContainer()
            ->get('doctrine')->getEntityManager();

            $empSedesAgencias = $em->createQuery("
              SELECT ESA FROM
              AppBundle:EmpresasSedesAgencias ESA
              WHERE ESA.idSedeAgencia = :idSAgencia
            ")->setParameter("idSAgencia",$session->get('idSedeAgencia'))
            ->getResult();


            if($session->get('rol') == 'Cajero'){//Validación para Cajeros.
              $query = $er->createQueryBuilder("ESA");
              foreach ($empSedesAgencias as $key => $value) {
                $query->orWhere("ESA.idEmpresaSedeAgencia = :idESA".$key)
                ->setParameter("idESA".$key,$value->getIdEmpresaSedeAgencia());
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
            'data_class' => 'AppBundle\Entity\Cajas'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_cajas';
    }


}
