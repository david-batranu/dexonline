$().ready(function() {
  /* Add our own icon. Kudos <http://stackoverflow.com/questions/16604842/adding-a-custom-context-menu-item-to-elfinder/> */
  elFinder.prototype.i18.en.messages['cmdtagimage'] = 'Etichetează această imagine';
  elFinder.prototype._options.commands.push('tagimage');
  elFinder.prototype.commands.tagimage = function() {
    this.exec = function(hashes) {
      var hash = this.files()[0].hash;
      var url = this.fm.options.url + '?' + 'cmd=tagimage&target=' + hash;
      $.get(url, function(data) {
        window.location = wwwRoot + 'admin/visualTag?fileName=' + data.path;
      });
    }
    this.getstate = function() {
      //return 0 to enable, -1 to disable icon access
      var files = this.files();
      return (!this._disabled && (files.length == 1) && startsWith(files[0].mime, 'image/')) ? 0 : -1;
    }
  }

  elFinder.prototype.i18.en.messages['cmdtag3d'] = 'Etichetează 3D';
  elFinder.prototype._options.commands.push('tag3d');
  elFinder.prototype.commands.tag3d = function() {
    this.exec = function(hashes) {
      var hash = this.files()[0].hash;
      var url = this.fm.options.url + '?' + 'cmd=tagimage&target=' + hash;
      $.get(url, function(data) {
        window.location = wwwRoot + 'admin/visualTag3D.php?fileName=' + data.path;
      });
    }
    this.getstate = function() {
      //return 0 to enable, -1 to disable icon access
      var files = this.files();
      return (!this._disabled && (files.length == 1) && startsWith(files[0].mime, 'image/')) ? 0 : -1;
    }
  }

  $('#fileManager').elfinder({
    url: '../elfinder-connector/visual_connector.php',
    lang: 'en',
    uiOptions: {
      toolbar: [
        ['mkdir', 'upload'],
        ['copy', 'cut', 'paste'],
        ['download', 'rename', 'rm'],
        ['view', 'sort'],
        ['tagimage'],
        ['tag3d'],
        ['documentation'],
      ],
    },
    contextmenu: {
      // menu that opens when the user right-clicks on a file
      files: ['quicklook', 'download', '|', 'copy', 'cut', '|', 'rename', 'rm', '|', 'tagimage', 'tag3d'],
    },
    debug: true,
  }).elfinder('instance');

  // No icon tooltips.
  $(document).on('mouseenter', '.elfinder-cwd-file', function() {
    $(this).removeAttr('title');
  });

});
