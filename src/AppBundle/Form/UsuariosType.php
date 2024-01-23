<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;

use Symfony\Component\HttpFoundation\Session\Session;

class UsuariosType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('nombreCompleto',TextType::class,[
          "label"=>"Nombre: *",
          "attr"=>["class"=>"form-control"]
        ])
        ->add('identificacion',NumberType::class,[
          "label"=>"Identificación: *",
          "attr"=>["class"=>"form-control"]
        ])
        ->add('contrasena',PasswordType::class,[
          "label"=>"Contraseña: *",
          "attr"=>["class"=>"form-control"]
        ])
        ->add('telefono',NumberType::class,[
          "label"=>"Teléfono: ",
          "required"=>false,
          "attr"=>["class"=>"form-control"]
        ])
        ->add('activo',ChoiceType::class,[
          "label"=>"¿Activo?",
          "required"=>false,
          "choices"=>[
            "SI"=>1,
            "NO"=>0
          ],
          "attr"=>["class"=>"form-control"]
        ])
        ->add('idGrupoUsuario',EntityType::class,[
          "label"=>"Perfil: *",
          "attr"=>["class"=>"form-control"],
          "placeholder"=>"Seleccione",
          "class"=>"AppBundle:GruposUsuarios",
          "query_builder"=>function(EntityRepository $er){
              $session = new Session();
              $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
              $rol = $session->get("rol");

              $query = $er->createQueryBuilder("GU");

              if($rol == 'Administrador Agencias'){
                $query->andWhere("GU.grupoUsuario <> 'Superusuario'")
                ->andWhere("GU.grupoUsuario <> 'Administrador'")
                ->andWhere("GU.grupoUsuario <> 'Auditor'");
              }else if($rol == 'Administrador'){
                $query->andWhere("GU.grupoUsuario <> 'Superusuario'");
              }else if($rol == 'Auditor'){
                $query->andWhere("GU.grupoUsuario = 'Auditor'");
              }else if($rol == 'Cajero'){
                $query->andWhere("GU.grupoUsuario = 'Cajero'");
              }

              return $query;
          }
        ])
        ->add('idSedeAgencia',EntityType::class,[
          "label"=>"Sede: *",
          "attr"=>["class"=>"form-control"],
          "placeholder"=>"Seleccione",
          "class"=>"AppBundle:SedesAgencias",
          "query_builder"=>function(EntityRepository $er){
              $session = new Session();
              $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
              $rol = $session->get("rol");
              $idSA = $session->get("idSedeAgencia");

              $ESA = $em->getRepository("AppBundle:EmpresasSedesAgencias")
              ->findBy(
                array("idSedeAgencia"=>$idSA)
              );

              $query = $er->createQueryBuilder("SA")
              ->join("AppBundle:Agencias","A","WITH","A.idAgencia = SA.idAgencia")
              ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","ESA.idSedeAgencia = SA.idSedeAgencia");

              if($rol == 'Administrador Agencias'){
                $query->andWhere("A.idAgencia = :idAgencia")
                ->setParameter("idAgencia",$ESA[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());
              }else if($rol == 'Administrador' || $rol == 'Auditor'){
                $query->andWhere("ESA.idEmpresa = :idEmpresa")
                ->setParameter("idEmpresa",$ESA[0]->getIdEmpresa()->getIdEmpresa());
              }else if($rol == 'Cajero'){
                $query->andWhere("ESA.idSedeAgencia = :idSA")
                ->setParameter("idSA",$idSA);
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
            'data_class' => 'AppBundle\Entity\Usuarios'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_usuarios';
    }


}
