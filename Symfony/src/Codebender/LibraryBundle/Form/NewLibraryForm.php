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
            ->add('GitPath', 'hidden')
            ->add('Zip', 'file')
            ->add('HumanName', 'text', array('label' => 'Human Name: '))
            ->add('MachineName', 'hidden')
            ->add('Description', 'text', array('label' => 'Description: '))
            ->add('Url', 'text', array('label' => 'Info Url: ', 'required' => false, 'attr' => array('placeholder' => 'The url where you can find info about the library')))
            ->add('SourceUrl', 'text', array('label' => 'Source Url: ', 'required' => false, 'attr' => array('placeholder' => 'A link to the actual code of the library (i.e. zip, etc)')))
            ->add('Notes', 'textarea', array('label' => 'Notes for the library: ', 'required' => false, 'attr' => array('placeholder' => 'Notes for the people of codebender')))
            ->add('Go', 'submit', array('attr' => array('class' => 'btn')));


}

    public function getName()
    {
        return 'newLibrary';
    }

}
