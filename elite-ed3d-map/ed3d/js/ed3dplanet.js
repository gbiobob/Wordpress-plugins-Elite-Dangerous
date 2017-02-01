//--
var camera;
var controls;
var scene;
var light;
var renderer;

//-- Map Vars
var container;
var routes = [];
var lensFlareSel;

var Ed3dPlanet = {

  'container'   : null,
  'basePath'    : './',
  'jsonPath'    : null,
  'jsonContainer' : null,

  //-- Fog density save
  'fogDensity' : null,

  'radius' : 100,

  //-- Materials
  'material' : {
    'Trd' : new THREE.MeshBasicMaterial({
      color: 0xffffff
    }),
    'line' : new THREE.LineBasicMaterial({
      color: 0x0E7F88
    }),
    'white' : new THREE.MeshBasicMaterial({
      color: 0xffffff
    }),
    'orange' : new THREE.MeshBasicMaterial({
      color: 0xFF9D00
    }),
    'black' : new THREE.MeshBasicMaterial({
      color: 0x010101
    }),
    'lightblue' : new THREE.MeshBasicMaterial({
      color: 0x0E7F88
    }),
    'darkblue' : new THREE.MeshBasicMaterial({
      color: 0x16292B
    }),
    'selected' : new THREE.MeshPhongMaterial({
      color: 0x0DFFFF
    }),
    'transparent' : new THREE.MeshBasicMaterial({
      color: 0x000000,
      transparent: true,
      opacity: 0
    }),
    'glow_1' : null,
    'custom' : []

  },


  'colors'  : [],
  'textures' : {},

  /**
   * Init Ed3dPlanet map
   *
   */

  'init' : function(options) {

    // Merge options with defaults
    var options = $.extend({
        container: Ed3dPlanet.container,
        basePath: Ed3dPlanet.basePath
    }, options);

    Loader.start();

    //-- Set Option
    this.basePath          = options.basePath;
    this.container         = options.container;
    this.jsonPath          = options.jsonPath;
    this.jsonContainer     = options.jsonContainer;
    this.withHudPanel      = options.withHudPanel;
    this.hudMultipleSelect = options.hudMultipleSelect;
    this.startAnim         = options.startAnim;
    this.effectScaleSystem = options.effectScaleSystem;
    this.playerPos         = options.playerPos;

    //-- Init 3D map container
    $('#'+Ed3dPlanet.container).append('<div id="Ed3dPlanetmap"></div>');


    //-- Load dependencies

    if(typeof isMinified !== 'undefined') return Ed3dPlanet.launchMap();

    $.when(

        $.getScript(Ed3dPlanet.basePath + "vendor/three-js/OrbitControls.js"),
        $.getScript(Ed3dPlanet.basePath + "vendor/three-js/CSS3DRenderer.js"),
        $.getScript(Ed3dPlanet.basePath + "vendor/three-js/Projector.js"),
        $.getScript(Ed3dPlanet.basePath + "vendor/three-js/FontUtils.js"),
        $.getScript(Ed3dPlanet.basePath + "vendor/three-js/helvetiker_regular.typeface.js"),


        $.getScript(Ed3dPlanet.basePath + "vendor/tween-js/Tween.js"),

        $.Deferred(function( deferred ){
            $( deferred.resolve );
        })

    ).done(function() {

      Ed3dPlanet.launchMap();

    });

  },

  /**
   * Launch
   */

  'launchMap' : function() {

      Ed3dPlanet.loadTextures();

      Ed3dPlanet.initScene();

      // Add some scene enhancement
      Ed3dPlanet.skyboxStars();

      // Create HUD
      //HUD.create("Ed3dPlanetmap");
      Ed3dPlanet.createPlanet()

      Ed3dPlanet.showScene();

      // Animate
      animate();

  },

  /**
   * CreatePlanet
   */

  'createPlanet' : function() {

    var geometry = new THREE.SphereGeometry( Ed3dPlanet.radius, 32, 32 );

     var material =    new THREE.MeshPhongMaterial({
          color: 0x999999,
          emissive: 0x072534,
          side: THREE.DoubleSide,
          shading: THREE.FlatShading
        })
    var sphere = new THREE.Mesh( geometry, material );
    scene.add( sphere );


/*

   tc1=mod(atan2(sin(lon2-lon1)*cos(lat2),
           cos(lat1)*sin(lat2)-sin(lat1)*cos(lat2)*cos(lon2-lon1)),
           2*pi)
*/



    Ed3dPlanet.addPointCoord(43.8333300 , 4.3500000);

    Ed3dPlanet.addPointCoord(-24.6545100, 25.9085900);

    //Ed3dPlanet.addPointCoord(29.9546500, -90.0750700);




  },
  /**
   * Init Three.js scene
   */

  'addPointCoord' : function(lat, lng) {

    var phi   = (90-lat)*(Math.PI/180);
    var theta = (lng+180)*(Math.PI/180);

    x = -((Ed3dPlanet.radius) * Math.sin(phi)*Math.cos(theta));
    z = ((Ed3dPlanet.radius) * Math.sin(phi)*Math.sin(theta));
    y = ((Ed3dPlanet.radius) * Math.cos(phi));

    var geometry = new THREE.SphereGeometry( 6, 32, 32 );

     var material =    new THREE.MeshPhongMaterial({
          color: 0x333333,
          emissive: 0x972534,
          side: THREE.DoubleSide,
          shading: THREE.FlatShading
        })
    var sphere = new THREE.Mesh( geometry, material );
    sphere.position.set(x,y,z);
    scene.add( sphere );

  },

  /**
   * Init Three.js scene
   */

  'loadTextures' : function() {

    //-- Load textures for lensflare
    var texloader = new THREE.TextureLoader();

    //-- Load textures
    this.textures.flare_white = texloader.load(Ed3dPlanet.basePath + "textures/lensflare/flare2.png");
    this.textures.flare_yellow = texloader.load(Ed3dPlanet.basePath + "textures/lensflare/star_grey2.png");
    this.textures.flare_center = texloader.load(Ed3dPlanet.basePath + "textures/lensflare/flare3.png");

    //-- Load sprites
    Ed3dPlanet.material.glow_1 = new THREE.SpriteMaterial({
      map: this.textures.flare_yellow,
      color: 0xffffff, transparent: false,
       fog: true
    });
    Ed3dPlanet.material.glow_2 = new THREE.SpriteMaterial({

      map: Ed3dPlanet.textures.flare_white, transparent: true, size: 15,
      vertexColors: THREE.VertexColors,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
      opacity: 0.5
    });

  },

  'addCustomMaterial' : function (id, color) {

    var color = new THREE.Color('#'+color);
    this.colors[id] = color;

  },


  /**
   * Init Three.js scene
   */

  'initScene' : function() {

    container = document.getElementById("Ed3dPlanetmap");

    //Scene
    scene = new THREE.Scene();
    scene.visible = false;
    /*scene.scale.set(10,10,10);*/

    //camera
    camera = new THREE.PerspectiveCamera(45, container.offsetWidth / container.offsetHeight, 1, 200000);
    //camera = new THREE.OrthographicCamera( container.offsetWidth / - 2, container.offsetWidth / 2, container.offsetHeight / 2, container.offsetHeight / - 2, - 500, 1000 );

    camera.position.set(0, 500, 500);

    //HemisphereLight
    light = new THREE.HemisphereLight(0x333333, 0x000000);
    scene.add(light);

    var directionalLight = new THREE.DirectionalLight( 0xffffff, 0.5 );
    directionalLight.position.set( 0.5, 0, 0.5 );
    scene.add( directionalLight );


    //WebGL Renderer
    renderer = new THREE.WebGLRenderer({
      antialias: true,
      alpha: true
    });
    renderer.setClearColor(0x000000, 1);
    renderer.setSize(container.offsetWidth, container.offsetHeight);
    renderer.domElement.style.zIndex = 5;
    container.appendChild(renderer.domElement);

    //controls
    controls = new THREE.OrbitControls(camera, container);
    controls.rotateSpeed = 1.0;
    controls.zoomSpeed = 3.0;
    controls.panSpeed = 0.8;
    controls.maxDistance = 40000;
    controls.enableZoom=1;controls.enablePan=1;controls.staticMoving=!0;controls.dynamicDampingFactor=.3;


    // Add Fog

    scene.fog = new THREE.FogExp2(0x0D0D10, 0.000128);
    renderer.setClearColor(scene.fog.color, 1);
    Ed3dPlanet.fogDensity = scene.fog.density;

  },

  /**
   * Show the scene when fully loaded
   */

  'showScene' : function() {

      Loader.stop();
      scene.visible = true;

  },


  'loadDatasComplete' : function() {

      //System.endParticleSystem();
      //HUD.init();
      //Action.init();

  },

  /**
   * Create a skybox of particle stars
   */

  'skyboxStars' : function() {

    var sizeStars = 10000;

    var particles = new THREE.Geometry;
    for (var p = 0; p < 500; p++) {
      var particle = new THREE.Vector3(
        Math.random() * sizeStars - (sizeStars / 2),
        Math.random() * sizeStars - (sizeStars / 2),
        Math.random() * sizeStars - (sizeStars / 2)
      );
      particles.vertices.push(particle);
    }

    var particleMaterial = new THREE.PointsMaterial({
      color: 0xeeeeee,
      size: 2
    });
    this.starfield = new THREE.Points(particles, particleMaterial);


    scene.add(this.starfield);
  },


  /**
   * Calc distance from Sol
   */

  'calcDistSol' : function(target) {

    var dx = target.x;
    var dy = target.y;
    var dz = target.z;

    return Math.round(Math.sqrt(dx*dx+dy*dy+dz*dz));
  }


}



//------------------------------------------------------------------------------
//------------------------------------------------------------------------------
//------------------------------------------------------------------------------
//------------------------------------------------------------------------------

function animate(time) {


  controls.update();


  renderer.render(scene, camera);

  //-- Move starfield with cam
  Ed3dPlanet.starfield.position.set(
    controls.target.x-(controls.target.x/10)%4000,
    controls.target.y-(controls.target.y/10)%4000,
    controls.target.z-(controls.target.z/10)%4000
  );


  requestAnimationFrame( animate );


}



function render() {
  renderer.render(scene, camera);
}


window.addEventListener('resize', function () {
  if(renderer != undefined) {
    var width = container.offsetWidth;
    var height = container.offsetHeight;
    if(width<100) width = 100;
    if(height<100) height = 100;
    renderer.setSize(width, height);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
  }
});












//--------------------------------------------------------------------------
// Test perf


function distance (v1, v2) {
    var dx = v1.position.x - v2.position.x;
    var dy = v1.position.y - v2.position.y;
    var dz = v1.position.z - v2.position.z;

    return Math.round(Math.sqrt(dx*dx+dy*dy+dz*dz));
}

function distanceFromTarget (v1) {
    var dx = v1.position.x - controls.target.x;
    var dy = v1.position.y - controls.target.y;
    var dz = v1.position.z - controls.target.z;

    return Math.round(Math.sqrt(dx*dx+dy*dy+dz*dz));
}

var camSave = {'x':0,'y':0,'z':0};


function refreshWithCamPos() {

  var d = new Date();
  var n = d.getTime();

  //-- Refresh only every 5 sec
  if(n % 1 != 0) return;

  Ed3dPlanet.grid1H.addCoords();
  Ed3dPlanet.grid1K.addCoords();

  //-- Refresh only if the camera moved
  var p = Ed3dPlanet.optDistObj/2;
  if(
    camSave.x == Math.round(camera.position.x/p)*p &&
    camSave.y == Math.round(camera.position.y/p)*p &&
    camSave.z == Math.round(camera.position.z/p)*p
  ) return;

  //-- Save new pos

  camSave.x = Math.round(camera.position.x/p)*p;
  camSave.y = Math.round(camera.position.y/p)*p;
  camSave.z = Math.round(camera.position.z/p)*p;

}
