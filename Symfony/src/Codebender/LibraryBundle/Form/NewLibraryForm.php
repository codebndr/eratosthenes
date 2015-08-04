<?php
namespace Codebender\LibraryBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class NewLibraryForm extends AbstractType{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('GitOwner', 'hidden')
            ->add('GitRepo', 'hidden')
            ->add('GitBranch', 'hidden')
            ->add('Zip', 'file')
            ->add('HumanName', 'text', array('label' => 'Human Name: '))
            ->add('MachineName', 'hidden')
            ->add('Description', 'text', array('label' => 'Description: '))
            ->add('Url', 'text', array('label' => 'Info Url: '))
            ->add('Go', 'submit');

    }

    public function getName()
    {
        return 'newLibrary';
    }

}
