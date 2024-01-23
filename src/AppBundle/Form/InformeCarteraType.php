<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Session\Session;

class InformeCarteraType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $session = new Session();
        $años = $this->getAniosPeriodos();
        $builder
        ->add('mesFacturacion',ChoiceType::class,array(
          "label"=>"Mes de Facturación:",
          "required"=>false,
          "attr"=>array("class"=>"form-control w-100","data-placeholder"=>"Seleccione"),
          "placeholder"=>"Seleccione",
          "data"=>$session->get("mesPeriodoActual"),
          "choices"=>[
            "Enero" => "1",
            "Febrero" => "2",
            "Marzo" => "3",
            "Abril" => "4",
            "Mayo" => "5",
            "Junio" => "6",
            "Julio" => "7",
            "Agosto" => "8",
            "Septiembre" => "9",
            "Octubre" => "10",
            "Noviembre" => "11",
            "Diciembre" => "12",
          ]
        ))->add('anioFacturacion',ChoiceType::class,array(
          "label"=>"Año de Facturación:",
          "required"=>false,
          "attr"=>array("class"=>"form-control w-100","data-placeholder"=>"Seleccione"),
          "placeholder"=>"Seleccione",
          'data' => $session->get("anioPeriodoActual"),
          "choices"=>$años
        ))->add("tipoInforme",ChoiceType::class,[
          "label" => "Tipo Informe",
          "attr" => ["class"=>"m-3 radio"],
          "data" => 1,
          "choices"=>array("Informe Compilado"=>1,"Informe Detallado"=>2),
          "expanded" => true,
          "multiple" => false
        ]);
    }/**
     * {@inheritdoc}
     */
     public function getAniosPeriodos(){
        $session = new Session();
        $años = [];
        $minAños = [];
        $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
        $rawem = $em->getConnection();
        $idSA = $session->get("idSedeAgencia");

        $EmpUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
        ->findBy(["idSedeAgencia"=>$idSA]);

        foreach ($EmpUsuario as $key => $value) {
            $queryfacturas = "SELECT MIN(F.anio_facturado) as anioMinimo FROM facturas as F WHERE F.id_empresa = ".$value->getIdEmpresa()->getIdEmpresa()."";

            $stmt = $rawem->prepare($queryfacturas);
            $rowAffected = $stmt->execute();
            $facturas = $stmt->fetchAll();
            array_push($minAños,$facturas[0]["anioMinimo"]);
        }

        asort($minAños);//Ordena Ascendentemente.
        $lenMax = count($minAños)+10;

        for($i=0;$i<=$lenMax;$i++){
          $años[$minAños[0]+$i] = ($minAños[0]+$i);
        }

        return $años;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_informecartera';
    }


}