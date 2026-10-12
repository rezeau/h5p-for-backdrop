/**
 * Explicit iframe compatibility installers for the pinned editor.
 */
(function (root) {
  'use strict';

  function installNestedLanguageUpdates(editor) {
    if (!editor || !editor.Form || !editor.Form.prototype ||
        typeof editor.Form.prototype.setSubContentDefaultLanguage !== 'function') {
      throw new Error('Load the H5P editor form before installing language compatibility.');
    }

    var prototype = editor.Form.prototype;
    var original = prototype.setSubContentDefaultLanguage;
    if (original.backdropNestedLanguageAdapter === true) {
      return false;
    }

    function updateLanguage(params, language) {
      if (Array.isArray(params)) {
        for (var i = 0; i < params.length; i++) {
          // Keep sparse entries unchanged, as the retained editor's map did.
          if (i in params) {
            params[i] = this.setSubContentDefaultLanguage(params[i], language);
          }
        }
        return params;
      }
      // Upstream handles objects/scalars and calls this method for recursion.
      return original.call(this, params, language);
    }
    updateLanguage.backdropNestedLanguageAdapter = true;
    prototype.setSubContentDefaultLanguage = updateLanguage;
    return true;
  }

  function installRichTextLanguage(editor) {
    if (!editor || !editor.Html || !editor.Html.prototype ||
        typeof editor.Html.prototype.getCKEditorConfig !== 'function') {
      throw new Error('Load the H5P HTML widget before installing rich-text language compatibility.');
    }
    var prototype = editor.Html.prototype;
    var original = prototype.getCKEditorConfig;
    if (original.backdropRichTextLanguageAdapter === true) {
      return false;
    }
    function getConfig() {
      var config = original.call(this);
      config.language = {
        ui: editor.uiLanguage === 'fr' ? 'fr' : 'en',
        content: editor.defaultLanguage || editor.contentLanguage || 'en'
      };
      return config;
    }
    getConfig.backdropRichTextLanguageAdapter = true;
    prototype.getCKEditorConfig = getConfig;
    return true;
  }

  var compatibility = {
    installNestedLanguageUpdates: installNestedLanguageUpdates,
    installRichTextLanguage: installRichTextLanguage
  };
  if (typeof module === 'object' && module.exports) {
    module.exports = compatibility;
  }
  else {
    root.H5PBackdropEditorCompatibility = compatibility;
  }
}(typeof window !== 'undefined' ? window : this));
