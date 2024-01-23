<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Session\Session;

class ParametrosType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('urlLogoEmpresa',UrlType::class,[
          "label"=>"URL Logo Empresa: *",
          "attr"=>["class"=>"form-control"]
        ])
        ->add('headerInformes',TextareaType::class,[
          "label"=>"Encabezado Informes: *",
          "attr"=>["class"=>"form-control"]
        ])
        ->add('porcentajeMetaRecaudo',NumberType::class,[
          "label"=>"% Meta Recaudo: *",
          "attr"=>["class"=>"form-control","type"=>"number"]
        ])
        ->add('idEmpresa',EntityType::class,[
          "label"=>"Empresa: *",
          "attr"=>["class"=>"form-control"],
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
            $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();

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
        ]);
    }/**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Parametros'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_parametros';
    }


}
