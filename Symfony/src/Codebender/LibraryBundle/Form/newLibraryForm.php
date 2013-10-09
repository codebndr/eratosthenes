<?php
namespace Codebender\LibraryBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class newLibraryForm extends AbstractType{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('GitOwner', 'hidden')
            ->add('GitRepo', 'hidden')
            ->add('Zip', 'file')
            ->add('HumanName', 'text', array('label' => 'Human Name: '))
            ->add('MachineName', 'hidden')
            ->add('Description', 'text', array('label' => 'Description: '))
            ->add('Go', 'submit');

    }

    public function getName()
    {
        return 'newLibrary';
    }

}
