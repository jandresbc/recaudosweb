<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class setPasswordType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('contrasena', RepeatedType::class, array(
            'type' => PasswordType::class,
            'invalid_message' => '
Los campos de contraseña deben coincidir.',
            'options' => array('attr' => array('class' => 'form-control w-50')),
            'required' => true,
            'first_options'  => array('label' => 'Nueva Contraseña: *'),
            'second_options' => array('label' => 'Repetir Contraseña: *'),
        ));
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
