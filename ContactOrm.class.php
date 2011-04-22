<?php

class ContactOrm extends IdiormRecord {

    public static function getTableName()
    {
        return 'contact';
    }
    
    public function getName()
    {
        return $this->get('name');
    }

    public function setName($name)
    {
        $this->set('name', $name);
    }

    public function getId()
    {
        return $this->get('id');
    }

    public function setId($id)
    {
        $this->set('id', $id);
    }

    public function getEmail()
    {
        return $this->get('email');
    }

    public function setEmail($email)
    {
        $this->set('email', $email);
    }
    
}

