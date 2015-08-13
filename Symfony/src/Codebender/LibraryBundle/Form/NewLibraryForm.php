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
            ->add('GitSha', 'hidden')
            ->add('Zip', 'file')
            ->add('HumanName', 'text', array('label' => 'Human Name: '))
            ->add('MachineName', 'hidden')
            ->add('Description', 'text', array('label' => 'Description: '))
            ->add('Url', 'text', array('label' => 'Info Url: '))
            ->add('SourceUrl', 'text', array('label' => 'Source Url: '))
            ->add('Go', 'submit', array('attr' => array('class' => 'btn')));

    }

    public function getName()
    {
        return 'newLibrary';
    }

}
