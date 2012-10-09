<?php

class Acl extends Permission {

    private static $instance = false;

    public function __construct() {

        $model = new AclModel();

        // roles
        foreach($model->getRoles() as $role)
            $this->addRole($role->code, $role->parent_code);

        // resources
        foreach($model->getResources() as $resource)
            $this->addResource($resource->code);
        $this->addResource('Default');

        // permission
        $this->allow(Permission::ALL, 'Default');
        $this->allow(Permission::ALL, 'ErrorPresenter');
        $this->allow(Permission::ALL, 'Spisovka_ErrorPresenter');
        
        // P.L. tato nesmyslná oprávnění (zejména cron) neměla v programu vůbec být
        // takto je musíme pro všechny uživatele povolit, protože to bude jednodušší než tvořit aktualizační skript, který je z databáze smaže
        // Uživatel stále bude mít možnost oprávnění explicitně odepřít
        $this->allow(Permission::ALL, 'Spisovka_ZpravyPresenter');
        $this->allow(Permission::ALL, 'Spisovka_CronPresenter');

        foreach($model->getPermission() as $perm) {
            if ( !empty($perm->role) && !empty($perm->resource) && !empty($perm->privilege) ) {
                // role + resource + privilege
                $this->{$perm->allowed == 'Y' ? 'allow' : 'deny'}($perm->role, $perm->resource, $perm->privilege);
            } else if ( !empty($perm->role) && !empty($perm->resource) && empty($perm->privilege) ) {
                // role + resource
                $this->{$perm->allowed == 'Y' ? 'allow' : 'deny'}($perm->role, $perm->resource);
            } else if ( !empty($perm->role) && empty($perm->resource) && empty($perm->privilege) ) {
                // role
                $this->{$perm->allowed == 'Y' ? 'allow' : 'deny'}($perm->role);
            } else if ( empty($perm->role) && !empty($perm->resource) && empty($perm->privilege) ) {
                // resource
                $this->{$perm->allowed == 'Y' ? 'allow' : 'deny'}(Permission::ALL, $perm->resource);
            } else if ( empty($perm->role) && empty($perm->resource) && !empty($perm->privilege) ) {
                // privilege
                $this->{$perm->allowed == 'Y' ? 'allow' : 'deny'}(Permission::ALL, Permission::ALL, $perm->privilege);
            } else if ( !empty($perm->role) && empty($perm->resource) && !empty($perm->privilege) ) {
                // role + privilege
                $this->{$perm->allowed == 'Y' ? 'allow' : 'deny'}($perm->role, Permission::ALL, $perm->privilege);
            } else if ( empty($perm->role) && !empty($perm->resource) && !empty($perm->privilege) ) {
                // resource + privilege
                $this->{$perm->allowed == 'Y' ? 'allow' : 'deny'}(Permission::ALL, $perm->resource, $perm->privilege);
            }
        }
        

    }

    public static function getInstance() {

        if(self::$instance === false){
            self::$instance = new Acl();
            return self::$instance;
        } else {
            return self::$instance;
        }

    }


    public function allowed($resource = self::ALL, $privilege = self::ALL) {

        $user_role = Environment::getUser()->getRoles();
        $allow = 0;

        //Debug::dump($user_role);

        foreach ($user_role as $role) {
            echo $role ." - ". $resource ." - ". $privilege;
            $opravneni = $this->allowedByRole($role, $resource, $privilege);
            //Debug::dump($opravneni);
            if ( count($opravneni)>0 ) {
                if ( $allow == 0 ) $allow = 1;
            }
        }

        return ($allow==1);

    }


    /**
    * Returns the "oldest" ancestor(s) in @role's genealogy that has/have the permission for @resource and @privilege
    * @uses Let the parameter @oldest set to zero!
    *
    * @param string|array $role
    * @param string|array $resource
    * @param mixed $privilege
    * @param int $oldes
    *
    * @return array
    */
    public function allowedByRole($role = self::ALL, $resource = self::ALL, $privilege = self::ALL, $oldest = 0) {

       # Assume that @role doesn't have the permission for @resource and @privilege
       $result = array(
         "oldest" => $oldest,
         "role" => array()
       );

       if ($role != self::ALL) {
         if ($this->isAllowed($role, $resource, $privilege)) {
           # Set @role as result and improve gradually
           $result = array(
             "oldest" => $oldest,
             "role" => array($role)
           );
           $parents = $this->getRoleParents($role);

           if (count($parents)) {
             foreach ($parents as $parent) {
               $value = $this->allowedByRole($parent, $resource, $privilege, $oldest + 1);

               if ($value['oldest'] > $oldest && count($value['role'])) {
                 $result = $value;
               } elseif ($value['oldest'] == $oldest && count($value['role'])) {
                 $result['role'] += $value['role'];
               }
             }
           }
         }
       }

       if ($oldest == 0) {
         return $result['role']; # final result
       } else {
         return $result; # result during recursion
       }
    }

    public static function isInRole($roles)
    {
        
        $Acl = new Acl();
        
        $user_roles = array();
        $roles_a = array();
        
        $user_roles = Environment::getUser()->roles;
        foreach ( $user_roles as $user_role ) {
            $user_roles = array_merge($user_roles, $Acl->getRoleParents($user_role));
        }
        $user_roles = array_flip($user_roles);
        
        if ( strpos($roles,",") !== false ) {
            $roles_a = explode(",",$roles);
            if ( count($roles_a)>0 ) {
                foreach( $roles_a as $role ) {
                    if ( isset($user_roles[$role]) ) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            return isset($user_roles[ $roles ]);
        }
        
    }
    
}
