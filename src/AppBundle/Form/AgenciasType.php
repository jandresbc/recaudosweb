<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\HttpFoundation\Session\Session;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;

class AgenciasType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('nombreAgencia',TextType::class,array(
          "label"=>"Nombre Agencia: *",
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))->add('nitAgencia',TextType::class,array(
          "label"=>"Nit Agencia: *",
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))->add('direccion',TextType::class,array(
          "label"=>"Dirección: *",
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))->add('telCel',TextType::class,array(
          "label"=>"Teléfono / Celular:",
          "required"=>false,
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))->add('inactiva',ChoiceType::class,array(
          "label"=>"¿Inactiva?:",
          "required"=>false,
          "placeholder"=>"Seleccione",
          "choices"=>[
            "SI" => 1,
            "NO" => 0
          ],
          "attr"=>array("class"=>"form-control w-100 m-1")
        ))->add('agenciasEmpresas',EntityType::class,array(
          "label"=>"Empresa: *",
          "class"=>"AppBundle:Empresas",
          "attr"=>array("class"=>"form-control w-100 m-1 select2"),
          "choice_attr"=>function($id,$key,$value){
            if(isset($GLOBALS["empresaAgencia"])){
              if($value == $GLOBALS["empresaAgencia"]){
                return ["selected"=>"selected"];
              }else{
                return ["value"=>$value];
              }
            }else{
              return ["value"=>$value];
            }
          },"query_builder"=>function(EntityRepository $er){
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
            'data_class' => 'AppBundle\Entity\Agencias'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_agencias';
    }


}
