<?php
namespace Roller;
use Iterator;
use Roller\RouteCompiler;
use Exception;
use ReflectionClass;
use ReflectionMethod;

class RouteSet implements Iterator
{
    public $routesMap = array();

    public $before = array();

    public $routes = array();

    public $i = 0;

    public function __call($m,$a) {
        switch( $m ) {
        case 'post':
        case 'head':
        case 'get':
            $path = $a[0];
            $callback = $a[1];
            $options = isset($a[2]) ? $a[2] : array();
            $options[':method'] = $m;
            return $this->add( $path , $callback , $options );
            break;
        }
        throw new Exception("Method $m not found.");
    }



    /**
     * __call magic is always slow than methods 
     */
    public function get($path, $callback, $options = array() )
    {
        $options[':method'] = 'get';
        return $this->add( $path,  $callback , $options );
    }

    public function post($path, $callback, $options = array() )
    {
        $options[':method'] = 'post';
        return $this->add( $path, $callback, $options );
    }

    public function importAnnotationFromReflectionMethod( ReflectionMethod $reflMethod ) 
    {
        $reader = $this->getAnnotationReader();
        $methodAnnotations = $reader->getMethodAnnotations($reflMethod);
        $cnt = 0;
        foreach( $methodAnnotations as $ma ) {
            $route = $ma->toRoute();
            $route['callback'] = array( 
                $reflMethod->class,
                $reflMethod->name,
            );
            $this->routes[] = $this->routeMap[ $ma->name ] = $route;
            $cnt++;
        }
        return $cnt;
    }

    public function importAnnotationMethods($class,$methods = null)
    {
        $reader = $this->getAnnotationReader();
        $reflClass = new ReflectionClass($class);

        if( is_array($methods) ) {
            foreach( $methods as $method ) {
                $reflMethod = $reflClass->getMethod($method);
                $this->importAnnotationFromReflectionMethod( $reflMethod );
            }
        }
        elseif( is_string($methods) && $methods[0] == '/' ) {
            $pattern = $methods;
            $methods = $reflClass->getMethods( ReflectionMethod::IS_PUBLIC );
            $methods = array_filter( $methods , function($m) use ($pattern) { 
                return preg_match( $pattern , $m->getName() );
            });
            foreach( $methods as $reflMethod ) {
                $this->importAnnotationFromReflectionMethod( $reflMethod );
            }
        }
        elseif ( is_string( $methods ) ) {
            $reflMethod = $reflClass->getMethod( $methods );
            $this->importAnnotationFromReflectionMethod( $reflMethod );
        }
        else {
            throw new Exception("Unknown methods type.");
        }
    }

    /**
     * using pure php to build route
     */
    protected function _buildRoute($path,$callback,$options = array() )
    {
        $route = array(
            'path' => null,
            'args' => null,
        );
        $route['path']        = $path;

        if( is_string($callback) && false !== strpos($callback,':') ) {
            $callback = explode(':',$callback);
        }
        $route['callback']    = $callback;


        $requirement = array();
        if( isset($options[':requirement']) ) {
            $requirement = $options[':requirement'];
        } else {
            foreach( $options as $k => $v ) {
                if( $k[0] !== ':' ) {
                    $requirement[ $k ] = $v;
                }
            }
        }
        $route['requirement'] = $requirement;


        /* :secure option for https */
        if( isset($options[':secure']) ) {
            $route['secure'] = true;
        }

        if( isset($options[':default']) ) {
            $route['default'] = $options[':default'];
        }


        if( isset($options[':method']) ) {
            $route['method'] = $options[':method'];
        } 
        elseif( isset($options[':post']) ) {
            $route['method'] = 'post';
        } 
        elseif( isset($options[':get']) ) {
            $route['method'] = 'get';
        } 
        elseif( isset($options[':head']) ) {
            $route['method'] = 'head';
        } 
        elseif( isset($options[':put']) ) {
            $route['method'] = 'put';
        } 
        elseif( isset($options[':delete']) ) {
            $route['method'] = 'delete';
        }

        if( isset($options[':before']) ) {
            $route['before'] = true;
        }

        /** 
         * arguments pass to constructor 
         */
        if( isset($options[':args']) ) {
            $route['args'] = $options[':args'];
        }

        // always have a name
        if( isset($options[':name']) ) {
            $route['name'] = $options[':name'];
        } else {
            $route['name'] = preg_replace( '/\W+/' , '_' , $route['path'] );
        }
        return $route;
    }





    /** 
     *
     * @param string $path
     * @param mixed $callback
     * @param array $options
     */
    public function any($path, $callback, $options = array() )
    {
        $route = null;
        if( function_exists('roller_build_route') ) {
            $route = roller_build_route( $path, $callback , $options );
        } else {
            $route = $this->_buildRoute( $path ,$callback, $options );
        }
        return $this->routes[] = $this->routesMap[ $route['name'] ] = & $route;
    }


    public function add($path,$callback,$options = array() )
    {
        return $this->any( $path, $callback, $options );
    }

    /**
     * find route by route path
     *
     */
    public function findRouteByPath( $path ) 
    {
        foreach( $this->routes as $route ) {
            if( isset($route['path']) && $route['path'] == $path ) {
                return $route;
            }
        }
    }


    /**
     * get route by route name
     */
    public function getRoute($name) 
    {
        if( isset($this->routesMap[ $name ] ) ) {
            return $this->routesMap[ $name ];
        }
    }


    // xxx: write this in extension.
    public function mount( $prefix, RouteSet $routes )
    {
        foreach( $routes as $r ) {
            $r['path'] = $prefix . rtrim($r['path'],'/');
            $this->routes[] = $r;
        }
    }


    // xxx: write this in extension to improve compile time performance.
    public function compile()
    {
        foreach( $this->routes as &$r ) {
            $r = RouteCompiler::compile($r);
        }
    }


    /** interface for iterating **/
    public function current() 
    {
        return $this->routes[ $this->i ];
    }

    public function key () {
        return $this->i;
    }

    public function next () {
        ++$this->i;
    }

    public function rewind () {
        $this->i = 0;
    }

    public function valid () {
        return isset( $this->routes[ $this->i ] );
    }


    /** interface for loading cache from source */
    static function __set_state($data)
    {
        $a = new self;
        $a->routes = $data['routes'];
        $a->i = $data['i'];
        return $a;
    }


    public function getAnnotationReader()
    {
        static $reader;
        if( $reader )
            return $reader;
        $reader = new \Doctrine\Common\Annotations\AnnotationReader();
        class_exists('Roller\Annotations\Route',true);
        return $reader;
    }


}

