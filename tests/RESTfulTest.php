<?php

use Roller\Plugin\RESTful\ResourceHandler;
use Roller\Plugin\RESTful\GenericHandler;

class MyGenericHandler extends GenericHandler
{
    public function create($resource) { 
        return array( 'id' => 99 );
    }

    public function load($resource,$id) { 
        return array( 'id' => $id );
    }

    public function update($resource,$id) { 
        return array( 'id' => $id );
    }

    public function delete($resource,$id) { 
        return array( 'id' => $id );
    }

    public function find($resource) { 
        return range(1,10);
    }

}

class BlogResourceHandler extends ResourceHandler
{
    public function create()    { 
        return array( 'id' => 1 );
    }

    public function update($id) { 
        return array( 'id' => 1 );
    }

    public function delete($id) { 
        return array( 'id' => 'delete' );
    }

    public function load($id)   { 
        return array( 'id' => $id , 'title' => 'title' );
    }

    public function find()      { 
        return array( 
            array( 'id' => 0 ),
            array( 'id' => 1 ),
            array( 'id' => 2 ),
        );
    }
}

class RESTfulTest extends PHPUnit_Framework_TestCase
{

    function testGenericHandler()
    {
        $router = new Roller\Router;
        ok( $router );

        $restful = new Roller\Plugin\RESTful(array( 'prefix' => '/restful' ));
        ok( $restful );

        $restful->setGenericHandler( 'MyGenericHandler' );
        $router->addPlugin($restful);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $r = $router->dispatch( '/restful/blog' );
        is( '[1,2,3,4,5,6,7,8,9,10]' , $r() );

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $r = $router->dispatch( '/restful/blog/3' );
        is( '{"id":"3"}' , $r() );

    }



    function test()
    {
        $router = new Roller\Router;
        ok( $router );

        $restful = new Roller\Plugin\RESTful(array( 'prefix' => '/restful' ));
        ok( $restful );

        $restful->registerResource( 'blog' , 'BlogResourceHandler' );
        $router->addPlugin($restful);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $r = $router->dispatch('/restful/blog/1');
        is( '{"id":"1","title":"title"}' , $r() );

        $_SRVER['REQUEST_METHOD'] = 'GET';
        $r = $router->dispatch('/restful/blog');
        is( '[{"id":0},{"id":1},{"id":2}]' , $r() );

        $_SRVER['REQUEST_METHOD'] = 'PUT';
        $r = $router->dispatch('/restful/blog/1');
        ok( $r() );

        $_SRVER['REQUEST_METHOD'] = 'DELETE';
        $r = $router->dispatch('/restful/blog/1');
        ok( $r() );

        $_SRVER['REQUEST_METHOD'] = 'POST';
        $r = $router->dispatch('/restful/blog');
        ok( $r() );
    }
}

