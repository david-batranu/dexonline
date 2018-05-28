<?php

class Lexeme extends BaseObject implements DatedObject {
  public static $_table = 'Lexeme';

  private $mt = null;  // ModelType object, but we call it $mt because there is already a DB field called 'modelType'
  private $sourceNames = null;         // Comma-separated list of source names
  private $inflectedForms = null;
  private $inflectedFormMap = null;    // Mapped by various criteria depending on the caller
  private $objectTags = null;
  private $fragments = null;           // for compound lexemes
  private $compoundParts = null;
  private $tags = null;
  private $animate = null;
  public $entries = null;

  const METHOD_GENERATE = 1;
  const METHOD_LOAD = 2;

  const CAN_DELETE_OK = null;
  const CAN_DELETE_LOC = 'lexemul nu poate fi șters deoarece este inclus în LOC';
  const CAN_DELETE_FRAGMENT = 'lexemul nu poate fi șters deoarece este fragment al unui lexem compus';

  function setForm($form) {
    $this->form = $form;
    $this->formNoAccent = str_replace("'", '', $form);
    $this->formUtf8General = $this->formNoAccent;
    $this->reverse = Str::reverse($this->formNoAccent);
  }

  static function create($form, $modelType = '', $modelNumber = '', $restriction = '',
                                $isLoc = false) {
    $l = Model::factory('Lexeme')->create();

    $form = trim($form);
    if (preg_match('/^(.*) \((.*)\)$/', $form, $matches)) {
      $l->setForm($matches[1]);
      $l->description = $matches[2];
    } else {
      $l->setForm($form);
      $l->description = '';
    }

    $l->noAccent = false;
    $l->modelType = $modelType;
    $l->modelNumber = $modelNumber;
    $l->restriction = $restriction;
    $l->notes = '';
    $l->isLoc = $isLoc;

    return $l;
  }

  function getModelType() {
    if ($this->mt === null) {
      $this->mt = ModelType::get_by_code($this->modelType);
    }
    return $this->mt;
  }

  function getPartOfSpeeech() {
    if ($this->modelType == 'I') {
      $model = FlexModel::loadCanonicalByTypeNumber($this->modelType, $this->modelNumber);
      return $model ? $model->description : '';
    } else {
      return $this->getModelType()->description;
    }
  }

  function hasModel($type, $number) {
    return ($this->modelType == $type) && ($this->modelNumber == $number);
  }

  function hasParadigm() {
    return $this->modelType != 'T';
  }

  function getFragments() {
    if ($this->fragments === null) {
      $this->fragments = Model::factory('Fragment')
                       ->where('lexemeId', $this->id)
                       ->order_by_asc('rank')
                       ->find_many();
    }
    return $this->fragments;
  }

  function setFragments($fragments) {
    $this->fragments = $fragments;
  }

  function getCompoundParts() {
    if ($this->compoundParts === null) {
      $this->compoundParts = [];
      foreach ($this->getFragments() as $f) {
        $this->compoundParts[] = Lexeme::get_by_id($f->partId);
      }
    }
    return $this->compoundParts;
  }

  function getSourceNames() {
    if ($this->sourceNames === null) {
      $sources = $this->getSources();
      $results = Util::objectProperty($sources, 'shortName');
      $this->sourceNames = implode(', ', $results);
    }
    return $this->sourceNames;
  }

  function getObjectTags() {
    if ($this->objectTags === null) {
      $this->objectTags = ObjectTag::getLexemeTags($this->id);
    }
    return $this->objectTags;
  }

  function setObjectTags($objectTags) {
    $this->objectTags = $objectTags;
  }

  function getTags() {
    if ($this->tags === null) {
      $this->tags = [];
      foreach ($this->getObjectTags() as $ot) {
        $this->tags[] = Tag::get_by_id($ot->tagId);
      }
    }
    return $this->tags;
  }

  function getTagIds() {
    $results = [];
    foreach ($this->getObjectTags() as $ot) {
      $results[] = $ot->tagId;
    }
    return $results;
  }

  function isAnimate() {
    if ($this->animate === null) {
      $this->animate = false;
      $animateValues = Config::get('tags.animateLexeme');
      foreach ($this->getTags() as $t) {
        if (in_array($t->value, $animateValues)) {
          $this->animate = true;
        }
      }
    }
    return $this->animate;
  }

  function setAnimate($animate) {
    $this->animate = $animate;
  }

  static function loadByExtendedName($extName) {
    $parts = preg_split('/\(/', $extName, 2);
    $name = addslashes(trim($parts[0]));
    if (count($parts) == 2) {
      $description = addslashes(trim($parts[1]));
      $description = str_replace(')', '', $description);
    } else {
      $description = '';
    }
    return Model::factory('Lexeme')->where('formNoAccent', $name)->where('description', $description)->find_many();
  }

  // For V1, this loads all lexeme models in (V1, VT1)
  static function loadByCanonicalModel($modelType, $modelNumber, $limit = 0) {
    $q = Model::factory('Lexeme')
      ->table_alias('l')
      ->select('l.*')
      ->join('ModelType', 'l.modelType = mt.code', 'mt')
      ->where('mt.canonical', $modelType)
      ->where('l.modelNumber', $modelNumber)
      ->order_by_asc('l.formNoAccent');

    if ($limit) {
      $q = $q->limit($limit);
    }

    return $q->find_many();
  }

  static function searchApproximate($cuv) {
    return NGram::searchNGram($cuv);
  }

  static function getRegexpQuery($regexp, $hasDiacritics, $sourceIds) {
    $mysqlRegexp = Str::dexRegexpToMysqlRegexp($regexp);
    $field = $hasDiacritics ? 'formNoAccent' : 'formUtf8General';

    if ($sourceIds) {
      // Suppress warnings from idiorm's log query function, which uses vsprintf,
      // which trips on extra % signs.
      return @Model::factory('Lexeme')
        ->table_alias('l')
        ->join('EntryLexeme', ['l.id', '=', 'el.lexemeId'], 'el')
        ->join('EntryDefinition', ['el.entryId', '=', 'ed.entryId'], 'ed')
        ->join('Definition', ['ed.definitionId', '=', 'd.id'], 'd')
        ->where_raw("$field $mysqlRegexp")
        ->where_in('d.sourceId', $sourceIds);
    } else {
      // even where there is no sourceId, make sure the lexeme has associated entries
      // (fragments don't)
      return @Model::factory('Lexeme')
        ->table_alias('l')
        ->join('EntryLexeme', ['l.id', '=', 'el.lexemeId'], 'el')
        ->where_raw("$field $mysqlRegexp");
    }
  }

  static function searchRegexp($regexp, $hasDiacritics, $sourceIds, $count = false) {
    try {
      $q = self::getRegexpQuery($regexp, $hasDiacritics, $sourceIds);
      if ($count) {
        $result = $q
                ->select_expr('count(distinct l.id)', 'count')
                ->find_array();
        $result = $result[0]['count'];
      } else {
        $result = $q
                ->select('l.*')
                ->distinct()
                ->order_by_asc('l.formNoAccent')
                ->limit(1000)
                ->find_many();
      }
    } catch (Exception $e) {
      $result = $count ? 0 : []; // Bad regexp
    }

    return $result;
  }

  /**
   * For every set of lexemes having the same form and no description, load one of them at random.
   */
  static function loadAmbiguous() {
    // The key here is to create a subquery of all the forms appearing at least twice
    // This takes about 0.6s
    $query = 'select * from Lexeme ' .
      'join (select binary form as f from Lexeme group by form having count(*) > 1) dup ' .
      'on form = f ' .
      'where description = "" ' .
      'group by form ' .
      'having count(*) > 1';
    return Model::factory('Lexeme')->raw_query($query)->find_many();
  }

  /**
   * Counts lexemes not associated with any entries.
   **/
  static function countUnassociated() {
    return count(self::getUnassociated());
  }

  /**
   * Returns lexemes not associated with any entries. Lexemes can be associated directly or they can
   * be fragments of associated lexemes.
   **/
  static function getUnassociated() {
    $direct = 'select lexemeId as id from EntryLexeme';
    $fragments = 'select partId as id from Fragment';
    $subquery = "$direct union $fragments";
    $query = 'select l.* ' .
           'from Lexeme l ' .
           "left outer join ($subquery) used on l.id = used.id " .
           'where used.id is null';

    return Model::factory('Lexeme')->raw_query($query)->find_many();
  }

  /**
   * Returns an array of InflectedForms. These can be loaded from the disk ($method = METHOD_LOAD)
   * or generated on the fly ($method = METHOD_GENERATE).
   * Throws ParadigmException for METHOD_GENERATE if any inflection cannot be generated.
   **/
  function getInflectedForms($method) {
    return ($method == self::METHOD_LOAD)
      ? $this->loadInflectedForms()
      : $this->generateInflectedForms();
  }

  function loadInflectedForms() {
    if ($this->inflectedForms === null) {
      $this->inflectedForms = Model::factory('InflectedForm')
        ->where('lexemeId', $this->id)
        ->order_by_asc('inflectionId')
        ->order_by_asc('variant')
        ->find_many();
    }
    return ($this->inflectedForms);
  }

  // throws ParadigmException if any inflection cannot be generated
  function generateInflectedForms() {
    if ($this->inflectedForms === null) {

      $this->inflectedForms = [];

      if ($this->compound) {

        // generate forms for compound lexemes
        $inflections = Model::factory('Inflection')
                     ->table_alias('i')
                     ->select('i.*')
                     ->join('ModelType', ['i.modelType', '=', 'mt.canonical'], 'mt')
                     ->where('mt.code', $this->modelType)
                     ->order_by_asc('i.rank')
                     ->find_many();
        foreach ($inflections as $inflId => $i) {
          $ifs = $this->generateCompoundForms($i);
          $this->inflectedForms = array_merge($this->inflectedForms, $ifs);
        }

      } else {

        // generate forms for simple lexemes

        $model = FlexModel::loadCanonicalByTypeNumber($this->modelType, $this->modelNumber);
        $inflIds = DB::getArray("select distinct inflectionId from ModelDescription " .
                               "where modelId = {$model->id} order by inflectionId");

        foreach ($inflIds as $inflId) {
          $ifs = $this->generateInflectedFormWithModel($this->form, $inflId, $model->id);
          $this->inflectedForms = array_merge($this->inflectedForms, $ifs);
        }

      }
    }

    return $this->inflectedForms;
  }

  // for METHOD_GENERATE, throws ParadigmException if any inflection cannot be generated
  function getInflectedFormMap($method) {
    if ($this->inflectedFormMap === null) {
      $ifs = $this->getInflectedForms($method);
      $this->inflectedFormMap = InflectedForm::mapByInflectionRank($ifs);
    }
    return $this->inflectedFormMap;
  }

  function loadInflectedFormMap() {
    return $this->getInflectedFormMap(self::METHOD_LOAD);
  }

  // throws ParadigmException if any inflection cannot be generated
  function generateInflectedFormMap() {
    return $this->getInflectedFormMap(self::METHOD_GENERATE);
  }

  // throws ParadigmException if the given inflection cannot be generated
  function generateCompoundForms($infl) {
    if (!ConstraintMap::validInflection($infl->id, $this->restriction) ||
        ($infl->animate && !$this->isAnimate())) {
      return [];
    }

    $fragments = $this->getFragments();
    $parts = $this->getCompoundParts();  // lexemes
    $chunks = preg_split('/[-\s]/', $this->formNoAccent);

    if (count($chunks) != count($fragments)) {
      throw new ParadigmException(
        $infl->id,
        sprintf("Lexemul este compus din %d părți, dar ați indicat %d fragmente.",
                count($chunks), count($fragments))
      );
    }

    $forms = [];

    foreach ($parts as $i => $p) {
      $frag = $fragments[$i];
      $chunk = $chunks[$i];

      if ($frag->declension == Fragment::DEC_INVARIABLE) {
        // make sure the corresponding chunk of $this->formNoAccent matches
        // one of the inflected forms of $p
        $if = InflectedForm::get_by_lexemeId_formNoAccent($p->id, $chunk);
        if (!$if) {
          throw new ParadigmException(
            $infl->id,
            "Lexemul „{$p->form}” nu generează forma „{$chunk}”."
          );
        }
      } else {
        // Load a preferred order of inflections from the part's model type and declension.
        // Try them one by one until one generates a form.
        $inflections = Fragment::getInflections($infl, $p->modelType, $frag->declension);
        $i = 0;

        do {
          $if = InflectedForm::get_by_lexemeId_inflectionId_variant($p->id, $inflections[$i]->id, 0);
          $i++;
        } while (!$if);
      }

      $f = $if->form;

      if ($frag->capitalized) {
        // the first symbol could be an apostrophe
        if (Str::startsWith($f, "'")) {
          $f = "'" . Str::capitalize(substr($f, 1));
        } else {
          $f = Str::capitalize($f);
        }
      }

      $forms[] = $f;
    }

    $delimiter = (strpos($this->form, '-') === false) ? ' ' : '-';
    $f = implode($delimiter, $forms);
    return [ InflectedForm::create($f, $this->id, $infl->id, 0, true) ];
  }

  // throws ParadigmException if the given inflection cannot be generated
  function generateInflectedFormWithModel($form, $inflId, $modelId) {
    $inflection = Inflection::get_by_id($inflId);
    if ($inflection->animate && !$this->isAnimate()) {
      // animate inflections, like the vocative, require the lexeme to be animate
      return [];
    }

    $ifs = [];
    $mds = Model::factory('ModelDescription')
         ->where('modelId', $modelId)
         ->where('inflectionId', $inflId)
         ->order_by_asc('variant')
         ->order_by_asc('applOrder')
         ->find_many();

    $start = 0;
    while ($start < count($mds)) {
      $variant = $mds[$start]->variant;
      $recommended = $mds[$start]->recommended;

      // Identify all the md's that differ only by the applOrder
      $end = $start + 1;
      while ($end < count($mds) && $mds[$end]->applOrder != 0) {
        $end++;
      }

      if (ConstraintMap::validInflection($inflId, $this->restriction, $variant)) {
        $inflId = $mds[$start]->inflectionId;
        $accentShift = $mds[$start]->accentShift;
        $vowel = $mds[$start]->vowel;

        // Load and apply all the transforms from $start to $end - 1.
        $transforms = [];
        for ($i = $end - 1; $i >= $start; $i--) {
          $transforms[] = Transform::get_by_id($mds[$i]->transformId);
        }

        $result = FlexStr::applyTransforms($form, $transforms, $accentShift, $vowel);
        if (!$result) {
          throw new ParadigmException($inflId, 'Nu pot genera forma.');
        }
        $ifs[] = InflectedForm::create($result, $this->id, $inflId, $variant, $recommended);
      }

      $start = $end;
    }

    return $ifs;
  }

  /**
   * Deletes the lexeme's old inflected forms, if they exist, then saves the new ones.
   * Throws ParadigmException if any inflection cannot be generated.
   **/
  function regenerateParadigm() {
    if ($this->id) {
      InflectedForm::delete_all_by_lexemeId($this->id);
    }
    foreach ($this->generateInflectedForms() as $if) {
      $if->lexemeId = $this->id;
      $if->save();
    }
  }

  /**
   * Adds an isLoc field to every inflected form in the map. Assumes the map already exists.
   **/
  function addLocInfo() {
    // Build a map of inflection IDs not in LOC
    $ids = Model::factory('InflectedForm')
      ->table_alias('i')
      ->select('i.id')
      ->join('Lexeme', 'i.lexemeId = l.id', 'l')
      ->join('ModelType', 'l.modelType = mt.code', 'mt')
      ->join('Model', 'mt.canonical = m.modelType and l.modelNumber = m.number', 'm')
      ->join('ModelDescription', 'm.id = md.modelId and i.variant = md.variant and i.inflectionId = md.inflectionId', 'md')
      ->where('md.applOrder', 0)
      ->where('md.isLoc', 0)
      ->where('l.id', $this->id)
      ->find_array();
    $map = [];
    foreach ($ids as $rec) {
      $map[$rec['id']] = 1;
    }

    // Set the bit accordingly on every inflection in the map
    foreach ($this->inflectedFormMap as $ifs) {
      foreach ($ifs as $if) {
        $if->isLoc = !array_key_exists($if->id, $map);
      }
    }
  }

  // apply tags required by harmonization rules
  function harmonizeTags() {
    $hts = Model::factory('HarmonizeTag')
      ->where('modelType', $this->modelType)
      ->where_in('modelNumber', ['', $this->modelNumber])
      ->find_many();
    foreach ($hts as $ht) {
      ObjectTag::associate(ObjectTag::TYPE_LEXEME, $this->id, $ht->tagId);
    }
  }

  // change the model given the tags, according to harmonization rules
  function harmonizeModel($tagIds) {
    if (empty($tagIds)) {
      return;
    }

    $hm = Model::factory('HarmonizeModel')
      ->where('modelType', $this->modelType)
      ->where_in('modelNumber', ['', $this->modelNumber])
      ->where_in('tagId', $tagIds)
      ->find_one();

    if ($hm) {
      $this->modelType = $hm->newModelType;
      if ($hm->newModelNumber) {
        $this->modelNumber = $hm->newModelNumber;
      }
    }
  }

  function regenerateDependentLexemes() {
    if ($this->modelType == 'VT') {
      $infl = Inflection::loadParticiple();

      $pm = ParticipleModel::get_by_verbModel($this->modelNumber);
      $number = $pm->adjectiveModel;

      $this->_regenerateDependentLexemesHelper($infl, 'A', 'PT', $number);
    }
    if (in_array($this->modelType, ['V', 'VT'])) {
      $infl = Inflection::loadLongInfinitive();

      // there could be several forms - just load the first one
      $longInfinitive = InflectedForm::get_by_lexemeId_inflectionId($this->id, $infl->id);
      $are = $longInfinitive && Str::endsWith($longInfinitive->formNoAccent, 'are');
      $number = $are ? 113 : 107;

      $this->_regenerateDependentLexemesHelper($infl, 'F', 'IL', $number);
    }
  }

  private function _regenerateDependentLexemesHelper($infl, $genericType, $dedicatedType, $number) {
    $ifs = InflectedForm::get_all_by_lexemeId_inflectionId($this->id, $infl->id);

    foreach ($ifs as $if) {
      // look for an existing lexeme
      $l = Model::factory('Lexeme')
         ->where('formNoAccent', $if->formNoAccent)
         ->where_in('modelType', [$genericType, $dedicatedType])
         ->where('modelNumber', $number)
         ->find_one();
      if ($l) {
        if ($this->isLoc && !$l->isLoc) {
          $l->isLoc = true;
          $l->save();
          FlashMessage::addTemplate('lexemeAddedToLoc.tpl', [ 'lexeme' => $l ], 'info');
        }
      } else {
        // if a lexeme exists with this form, but a different model, give a warning
        $existing = Lexeme::get_by_formNoAccent($if->formNoAccent);
        if ($existing) {
          FlashMessage::addTemplate('lexemeExists.tpl', [ 'lexeme' => $existing ], 'warning');
        }

        $l = Lexeme::create($if->form, $dedicatedType, $number, '', $this->isLoc);
        $l->deepSave();
        $entry = Entry::createAndSave($if->formNoAccent);
        EntryLexeme::associate($entry->id, $l->id);

        // copy trees and structure information from one of the lexeme's entries
        $infEntries = $this->getEntries();
        if (!empty($infEntries)) {
          $infEntry = $infEntries[0];
          TreeEntry::copy($infEntry->id, $entry->id, 2);
          $entry->structStatus = $infEntry->structStatus;
          $entry->structuristId = $infEntry->structuristId;
          $entry->save();
        }

        $l->harmonizeTags();

        // Also associate the new entry with the same definitions as $this.
        foreach ($this->getEntries() as $e) {
          foreach ($e->getDefinitions() as $d) {
            EntryDefinition::associate($entry->id, $d->id);
          }
        }
        FlashMessage::addTemplate('dependentLexemeCreated.tpl', [ 'lexeme' => $l ], 'info');
      }
    }
  }

  /**
   * Called when the lexeme is deleted or its model type changes to a non-VT.
   * Only deletes PT participles, not A participles.
   */
  function deleteParticiple() {
    if ($this->modelType == 'V' || $this->modelType == 'VT') {
      $infl = Inflection::loadParticiple();
      $pm = ParticipleModel::get_by_verbModel($this->modelNumber);
      $this->_deleteDependentLexemes($infl->id, 'PT', [$pm->adjectiveModel]);
    }
  }

  /**
   * Called when the lexeme is deleted or its model type changes to a non-verb.
   * Only deletes IL long infinitives, not F long infinitives.
   */
  function deleteLongInfinitive() {
    $infl = Inflection::loadLongInfinitive();
    $this->_deleteDependentLexemes($infl->id, 'IL', ['107', '113']);
  }

  // deletes dependent lexemes and their entries
  private function _deleteDependentLexemes($inflId, $modelType, $modelNumbers) {
    // Iterate through all the forms of the desired inflection (participle / long infinitive)
    $ifs = InflectedForm::get_all_by_lexemeId_inflectionId($this->id, $inflId);
    foreach ($ifs as $if) {
      // Examine all lexemes having one of the above forms and model
      $lexemes = Model::factory('Lexeme')
              ->where('formNoAccent', $if->formNoAccent)
              ->where('modelType', $modelType)
              ->where_in('modelNumber', $modelNumbers)
              ->find_many();
      foreach ($lexemes as $l) {
        FlashMessage::add("Am șters automat lexemul {$l} și toate intrările asociate.", 'info');
        $entries = Model::factory('Entry')
                 ->table_alias('e')
                 ->select('e.*')
                 ->join('EntryLexeme', ['e.id', '=', 'el.entryId'], 'el')
                 ->where('el.lexemeId', $l->id)
                 ->find_many();
        foreach ($entries as $e) {
          $e->delete();
        }
        $l->delete();
      }
    }
  }

  // returns one of the CAN_DELETE_* constants
  function canDelete() {
    // only LOC moderators can delete LOC lexemes
    if ($this->isLoc && !User::can(User::PRIV_LOC)) {
      return self::CAN_DELETE_LOC;
    }

    // cannot delete lexemes which are fragments of compound lexemes
    if (Fragment::get_by_partId($this->id)) {
      return self::CAN_DELETE_FRAGMENT;
    }

    return self::CAN_DELETE_OK;
  }

  function delete() {
    if ($this->id) {
      if ($this->modelType == 'VT') {
        $this->deleteParticiple();
      }
      if ($this->modelType == 'VT' || $this->modelType == 'V') {
        $this->deleteLongInfinitive();
      }
      InflectedForm::delete_all_by_lexemeId($this->id);
      EntryLexeme::delete_all_by_lexemeId($this->id);
      LexemeSource::delete_all_by_lexemeId($this->id);
      ObjectTag::delete_all_by_objectId_objectType($this->id, ObjectTag::TYPE_LEXEME);
      Fragment::delete_all_by_lexemeId($this->id);
      Fragment::delete_all_by_partId($this->id);
      // delete_all_by_lexemeId doesn't work for FullTextIndex because it doesn't have an ID column
      Model::factory('FullTextIndex')->where('lexemeId', $this->id)->delete_many();
    }
    Log::warning("Deleted lexeme {$this->id} ({$this->formNoAccent})");
    parent::delete();
  }

  function save() {
    $this->formUtf8General = $this->formNoAccent;
    $this->reverse = Str::reverse($this->formNoAccent);
    $this->charLength = mb_strlen($this->formNoAccent);
    $this->consistentAccent = (strpos($this->form, "'") !== false) ^ $this->noAccent;
    // It is important for empty fields to be null, not "".
    // This allows queries for records with non-null values to run faster.
    if (!$this->number) {
      $this->number = null;
    }
    parent::save();
  }

  /**
   * Saves a lexeme and its dependants.
   **/
  function deepSave() {
    $this->save();

    Fragment::delete_all_by_lexemeId($this->id);
    InflectedForm::delete_all_by_lexemeId($this->id);
    ObjectTag::delete_all_by_objectId_objectType($this->id, ObjectTag::TYPE_LEXEME);

    foreach ($this->getFragments() as $f) {
      $f->lexemeId = $this->id;
      $f->save();
    }
    foreach ($this->generateInflectedForms() as $if) {
      $if->lexemeId = $this->id;
      $if->save();
    }
    foreach ($this->getObjectTags() as $ot) {
      $ot->objectId = $this->id;
      $ot->save();
    }
  }

  function __toString() {
    return $this->description ? "{$this->formNoAccent} ({$this->description})" : $this->formNoAccent;
  }

  function _clone() {
    $clone = $this->parisClone();
    $clone->description = ($this->description) ? "CLONĂ {$this->description}" : "CLONĂ";
    $clone->verifSp = false;
    $clone->compound = false;
    $clone->modelType = 'T';
    $clone->modelNumber = '1';
    $clone->restriction = '';
    $clone->notes = '';
    $clone->isLoc = false;
    $clone->deepSave();

    return $clone;
  }

}
