$(function() {

  function init() {
    var selects = [].slice.call(document.getElementsByTagName('select'));
    selects.forEach(function(select){
      var value = select.getElementsByTagName('option')[0].getAttribute('value');
      $(select).select2({
        ajax: { url: wwwRoot + 'ajax/getEntries.php' },
        minimumInputLength: 1,
        placeholder: 'caută o intrare',
        width: '300px',
      }).change(console.log);
    });
  }

  init();
});


window.start3d = (function(){

  var config = {
    resolution: {
      w: 800,
      h: 600
    },
  };

  var globals = {
    camera: new THREE.PerspectiveCamera(45, config.resolution.w / config.resolution.h, 1, 2000),
    scene: new THREE.Scene(),
    container: document.getElementById('preview-3d'),
    clock: new THREE.Clock(),
    center: { // canvas center
      x: config.resolution.w / 2,
      y: config.resolution.h / 2
    },
    ui: function(){
      return document.getElementById('overlay-2d')
        .getElementsByTagName('canvas')[0]
        .getContext('2d');
    }(),
    objects: [],
    renderer: null,
    controls: null
  };

  var materials = {
    highlight: new THREE.MeshPhongMaterial({
      color: new THREE.Color(1, 0 ,0)
    }),
    wireframe: new THREE.LineBasicMaterial({
      color: 0xffffff,
      linewidth: 1,
    })
  };

  var clone_events = [
    {
      events: [
        'mousedown',
        'mouseenter',
        'mouseleave',
        'mousemove',
        'mouseout',
        'mouseover',
        'mouseup',
        'scroll',
      ],
      trigger: MouseEvent
    },
    {
      events: ['wheel'],
      trigger: WheelEvent
    }
  ];


  var RESOLUTION = config.resolution;
  var UI = globals.ui;

  if (!Detector.webgl) { Detector.addGetWebGLMessage(); };

  var utils = (function(){
    function projectToScreen(obj, camera, resolution){
      var box = new THREE.Box3();
      box.setFromObject(obj);

      var pos = box.getCenter().clone();
      pos.project(camera);

      var widthHalf = resolution.w / 2;
      var heightHalf = resolution.h / 2;

      pos.x = (pos.x * widthHalf) + widthHalf;
      pos.y = - (pos.y * heightHalf) + heightHalf;
      pos.z = 0;

      return {
        x: pos.x,
        y: pos.y,
        obj: obj
      };
    }

    function tagFromPoint(point /* object coordinates */, cc /* canvas center */, resolution) {
      var pc = { // point center
        x: point.x + 1,
        y: point.y + 1
      }; // adding 1 to avoid points at 0, 0

      var ray = {
        a: pc.y - cc.y,
        b: cc.x - pc.x,
        c: cc.y * (pc.x - cc.x) - cc.x * (pc.y - cc.y)
      } // line through cc and pc


      var inters = [];
      if (ray.b) {

        inters.push({
          x: 0,
          y: -ray.c / ray.b
        });

        inters.push({
          x: resolution.w,
          y: (-ray.c - ray.a * resolution.w) / ray.b
        });

      }

      if (ray.a) {

        inters.push({
          x: -ray.c / ray.a,
          y: 0
        });

        inters.push({
          x: (-ray.c - ray.b * resolution.h) / ray.a,
          y: resolution.h
        });

      }

      if (!inters.length) { // can happen if pc == cc
        inters.push({x: 0, y: 0});
      }

      function dist_sq(p1, p2) {
        return (p2.x - p1.x) ** 2 + (p2.y - p1.y) ** 2;
      }

      var min_inter = inters.reduce(function(acc, p) {
        if (!acc) {
          return p
        };

        if (dist_sq(pc, acc) < dist_sq(pc, p)) {
          return acc
        };

        return p;
      }, null);

      var tag = {
        x: (min_inter.x + pc.x) / 2,
        y: (min_inter.y + pc.y) / 2
      }

      return tag;
    };

    function assetLoader(url, callback) {
      var manager = new THREE.LoadingManager();

      var onError = function(xhr) {
        console.log(xhr);
      };

      var onProgress = function(xhr) {
        if (xhr.lengthComputable) {
          var percentComplete = xhr.loaded / xhr.total * 100;
          console.log(Math.round(percentComplete, 2) + '% downloaded');
        }
      }

      var loader = new THREE.ColladaLoader(manager);
      loader.load(url, callback, onProgress, onError)

    };

    function wireframeFromGeometry(geometry, wireframeMaterial) {
      var wireframeGeometry = new THREE.EdgesGeometry(geometry);
      var wireframe = new THREE.LineSegments(wireframeGeometry, wireframeMaterial);
      wireframe.material.depthTest = false;
      wireframe.visible = false;
      return wireframe;
    }

    function huntForMeshes(objects, result) {
      objects.forEach(function(obj) {
        if (obj.children) {
          huntForMeshes(obj.children, result);
        }
        if (obj.type === 'Mesh') {
          result.push(obj);
        }
      });
    };

    function renderTag(center, tag, name, ui) {
      ui.beginPath();
      ui.moveTo(tag.x, tag.y);
      ui.lineTo(center.x, center.y);
      ui.lineWidth = 0.5;
      ui.stroke();
      ui.font = "12px Arial";
      ui.fillText(name, tag.x, tag.y);
    };

    function setupRenderer(resolution, container) {
      renderer = new THREE.WebGLRenderer();
      renderer.setPixelRatio(window.devicePixelRatio);
      renderer.setSize(RESOLUTION.w, RESOLUTION.h);
      renderer.setClearColor(0xededed);
      return renderer;
    }

    return {
      projectToScreen: projectToScreen,
      tagFromPoint: tagFromPoint,
      assetLoader: assetLoader,
      wireframeFromGeometry: wireframeFromGeometry,
      huntForMeshes: huntForMeshes,
      renderTag: renderTag,
      setupRenderer: setupRenderer
    }

  })();


  function init(loadCallback) {

    // pass events to 3d canvas
    clone_events.forEach(function(def) {
      def.events.forEach(function(evt_type) {
        UI.canvas.addEventListener(evt_type, function(evt){
          var new_evt = new def.trigger(evt.type, evt);
          globals.container.getElementsByTagName('canvas')[0].dispatchEvent(new_evt);
        });
      });
    });


    function gameLoop(config, globals, utils) {
      var renderer = globals.renderer;
      var scene = globals.scene;
      var camera = globals.camera;

      function render() {
        renderer.render(scene, camera);
      }

      function update() {
        // clear ui canvas
        UI.clearRect(0, 0, config.resolution.w, config.resolution.h);

        // render tags
        var projectToScreen = function(obj) {
          return utils.projectToScreen(obj, globals.camera, config.resolution);
        };
        if (globals.objects.length > 0 && globals.ui && utils.projectToScreen) {
          var centers = globals.objects.map(projectToScreen);
          centers.forEach(function(center, idx) {
            var tag = utils.tagFromPoint(center, globals.center, config.resolution);
            var name = center.obj.name ? center.obj.name : center.obj.parent.name;
            utils.renderTag(center, tag, name, globals.ui);
          });
        }

        // run forever
        requestAnimationFrame(update);
        render();
      }

      update();

    }

    // load scene
    var url = globals.container.getAttribute('data-src');

    utils.assetLoader(url, function(collada){
      var object = collada.scene;

      globals.objects = function() {
        var result = [];
        utils.huntForMeshes(object.children, result);
        return result;
      }();

      globals.objects.forEach(function(child){
        var wireframe = utils.wireframeFromGeometry(child.geometry);
        child.add(wireframe);
        child.wireframe = wireframe;
      });

      globals.scene.add(object);

      // setup renderer
      globals.renderer = utils.setupRenderer(config.resolution);
      globals.container.appendChild(globals.renderer.domElement);

      // setup controls
      globals.camera.position.set(0, 0, 20);

      controls = new THREE.OrbitControls(globals.camera, globals.renderer.domElement);
      controls.target.copy(object.position);
      controls.enablePan = false;
      controls.update();

      // ambient light
      // var light = new THREE.AmbientLight(0xffffff);
      // globals.scene.add(light);

      gameLoop(config, globals, utils);

    });
  }


//  function add_table_entry(name, elem) {
//    var cell_name_exists = document.getElementById(name);
//    if (cell_name_exists) {
//      cell_name_exists.appendChild(elem);
//    }
//    else {
//      var row = document.createElement('tr');
//      var cell_name = document.createElement('td');
//      var cell_assign = document.createElement('td');
//      var cell_camera = document.createElement('td');
//
//      var assign_select = document.createElement('select');
//      assign_select.setAttribute('name', 'mapping_' + name);
//
//      var elem_camera_input = document.createElement('input');
//      elem_camera_input.setAttribute('type', 'hidden');
//      elem_camera_input.setAttribute('id', 'camera_' + name);
//      elem_camera_input.setAttribute('name', 'camera_' + name);
//      elem_camera_input.setAttribute('value', encodeVector(camera.position));
//
//      function factory_camera_button(action, name, text) {
//        var elem = document.createElement('button');
//        elem.setAttribute('data-action', action);
//        elem.setAttribute('data-mesh', name);
//        elem.setAttribute('class', 'btn btn-sm btn-default');
//        elem.textContent = text;
//        return elem;
//      }
//
//      elem_camera_save = factory_camera_button('save-camera', name, 'Salvează poziția camerei');
//      elem_camera_restore = factory_camera_button('restore-camera', name, 'Aplică poziția camerei');
//
//      var tbody = document.getElementById('table-assign').getElementsByTagName('tbody')[0];
//
//      cell_name.appendChild(elem);
//      cell_assign.appendChild(assign_select);
//
//      cell_camera.appendChild(elem_camera_input);
//      cell_camera.appendChild(elem_camera_save);
//      cell_camera.appendChild(elem_camera_restore);
//
//      row.appendChild(cell_name);
//      row.appendChild(cell_assign);
//      row.appendChild(cell_camera);
//
//      tbody.appendChild(row);
//
//      $(assign_select).select2({
//        ajax: { url: wwwRoot + 'ajax/getEntries.php' },
//        minimumInputLength: 1,
//        placeholder: 'caută o intrare',
//        width: '300px',
//      }).change(console.log);
//    }
//
//  }
//
//  function init_camera_save() {
//    var buttons = [].slice.call(document.querySelectorAll('[data-action="save-camera"]'));
//    buttons.forEach(function(button) {
//      button.addEventListener('click', function(evt) {
//        evt.preventDefault();
//        var mesh = button.getAttribute('data-mesh');
//        var target = document.getElementById('camera_' + mesh);
//        target.setAttribute('value', encodeVector(camera.position));
//      });
//    });
//  }
//
//  function init_camera_restore() {
//    var buttons = [].slice.call(document.querySelectorAll('[data-action="restore-camera"]'));
//    buttons.forEach(function(button) {
//      button.addEventListener('click', function(evt) {
//        evt.preventDefault();
//        var mesh = button.getAttribute('data-mesh');
//        var target = document.getElementById('camera_' + mesh);
//        var saved = decodeVector(target.getAttribute('value'));
//        camera.position.set(saved.x, saved.y, saved.z);
//        controls.update();
//      });
//    });
//  }
//
//  function init_default_cameras() {
//    var inputs = [].slice.call(document.querySelectorAll('[id*="camera_"]'));
//    var camera_position = encodeVector(camera.position);
//    inputs.forEach(function(input){
//      if(input.getAttribute('value') === '') {
//        input.setAttribute('value', camera_position)
//      }
//    });
//  }
//
//  function encodeVector(value) {
//    return [value.x, value.y, value.z].join(',')
//  }
//
//  function decodeVector(value) {
//    var arr = value.split(',').map(parseFloat);
//    return new THREE.Vector3(arr[0], arr[1], arr[2])
//  }
//
//  init(function(){
//    init_default_cameras();
//    init_camera_save();
//    init_camera_restore();
//  });

  init();

});















//           var tag = document.createElement('a');
//           tag.setAttribute('href', '#');
//           tag.textContent = child.name ? child.name : child.parent.name;
//           tag.addEventListener('click', function(evt){
//             evt.preventDefault();
//             var obj = scene.getObjectById(child.id);
//             if (obj.material_bak) {
//               obj.material = obj.material_bak;
//               obj.wireframe.visible = false;
//               delete obj.material_bak;
//             }
//             else {
//               obj.material_bak = obj.material;
//               obj.material = materials.highlight;
//               obj.wireframe.visible = true;
//             }
//           });
//
//           add_table_entry(child.name, tag);
