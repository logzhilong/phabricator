<?php

final class DifferentialChangesetListView extends AphrontView {

  private $changesets = array();
  private $visibleChangesets = array();
  private $references = array();
  private $inlineURI;
  private $renderURI = '/differential/changeset/';
  private $whitespace;

  private $standaloneURI;
  private $leftRawFileURI;
  private $rightRawFileURI;

  private $symbolIndexes = array();
  private $repository;
  private $branch;
  private $diff;
  private $vsMap = array();

  private $title;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }
  private function getTitle() {
    return $this->title;
  }

  public function setBranch($branch) {
    $this->branch = $branch;
    return $this;
  }
  private function getBranch() {
    return $this->branch;
  }

  public function setChangesets($changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function setVisibleChangesets($visible_changesets) {
    $this->visibleChangesets = $visible_changesets;
    return $this;
  }

  public function setInlineCommentControllerURI($uri) {
    $this->inlineURI = $uri;
    return $this;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function setRenderingReferences(array $references) {
    $this->references = $references;
    return $this;
  }

  public function setSymbolIndexes(array $indexes) {
    $this->symbolIndexes = $indexes;
    return $this;
  }

  public function setRenderURI($render_uri) {
    $this->renderURI = $render_uri;
    return $this;
  }

  public function setWhitespace($whitespace) {
    $this->whitespace = $whitespace;
    return $this;
  }

  public function setVsMap(array $vs_map) {
    $this->vsMap = $vs_map;
    return $this;
  }

  public function getVsMap() {
    return $this->vsMap;
  }

  public function setStandaloneURI($uri) {
    $this->standaloneURI = $uri;
    return $this;
  }

  public function setRawFileURIs($l, $r) {
    $this->leftRawFileURI = $l;
    $this->rightRawFileURI = $r;
    return $this;
  }

  public function render() {
    $this->requireResource('differential-changeset-view-css');

    $changesets = $this->changesets;

    Javelin::initBehavior('differential-toggle-files', array(
      'pht' => array(
        'undo' => pht('Undo'),
        'collapsed' => pht('This file content has been collapsed.'),
      ),
    ));
    Javelin::initBehavior(
      'differential-dropdown-menus',
      array(
        'pht' => array(
          'Open in Editor' => pht('Open in Editor'),
          'Show Entire File' => pht('Show Entire File'),
          'Entire File Shown' => pht('Entire File Shown'),
          "Can't Toggle Unloaded File" => pht("Can't Toggle Unloaded File"),
          'Expand File' => pht('Expand File'),
          'Collapse File' => pht('Collapse File'),
          'Browse in Diffusion' => pht('Browse in Diffusion'),
          'View Standalone' => pht('View Standalone'),
          'Show Raw File (Left)' => pht('Show Raw File (Left)'),
          'Show Raw File (Right)' => pht('Show Raw File (Right)'),
          'Configure Editor' => pht('Configure Editor'),
          'Load Changes' => pht('Load Changes'),
          'View Side-by-Side' => pht('View Side-by-Side'),
          'View Unified' => pht('View Unified (Barely Works!)'),
          'Change Text Encoding...' => pht('Change Text Encoding...'),
          'Highlight As...' => pht('Highlight As...'),
        ),
      ));

    $output = array();
    $ids = array();
    foreach ($changesets as $key => $changeset) {

      $file = $changeset->getFilename();
      $class = 'differential-changeset';
      if (!$this->inlineURI) {
        $class .= ' differential-changeset-noneditable';
      }

      $ref = $this->references[$key];

      $detail = new DifferentialChangesetDetailView();

      $uniq_id = 'diff-'.$changeset->getAnchorName();
      $detail->setID($uniq_id);

      $view_options = $this->renderViewOptionsDropdown(
        $detail,
        $ref,
        $changeset);

      $detail->setChangeset($changeset);
      $detail->addButton($view_options);
      $detail->setSymbolIndex(idx($this->symbolIndexes, $key));
      $detail->setVsChangesetID(idx($this->vsMap, $changeset->getID()));
      $detail->setEditable(true);
      $detail->setRenderingRef($ref);
      $detail->setAutoload(isset($this->visibleChangesets[$key]));

      $detail->setRenderURI($this->renderURI);
      $detail->setWhitespace($this->whitespace);

      if (isset($this->visibleChangesets[$key])) {
        $load = 'Loading...';
      } else {
        $load = javelin_tag(
          'a',
          array(
            'class' => 'button grey',
            'href' => '#'.$uniq_id,
            'sigil' => 'differential-load',
            'meta' => array(
              'id' => $detail->getID(),
              'kill' => true,
            ),
            'mustcapture' => true,
          ),
          pht('Load File'));
      }
      $detail->appendChild(
        phutil_tag(
          'div',
          array(
            'id' => $uniq_id,
          ),
          phutil_tag('div', array('class' => 'differential-loading'), $load)));
      $output[] = $detail->render();

      $ids[] = $detail->getID();
    }

    $this->requireResource('aphront-tooltip-css');

    $this->initBehavior('differential-populate', array(
      'changesetViewIDs' => $ids,
    ));

    $this->initBehavior('differential-show-more', array(
      'uri' => $this->renderURI,
      'whitespace' => $this->whitespace,
    ));

    $this->initBehavior('differential-comment-jump', array());

    if ($this->inlineURI) {
      $undo_templates = $this->renderUndoTemplates();

      Javelin::initBehavior('differential-edit-inline-comments', array(
        'uri'             => $this->inlineURI,
        'undo_templates'  => $undo_templates,
        'stage'           => 'differential-review-stage',
      ));
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($this->getTitle());

    $content = phutil_tag(
      'div',
      array(
        'class' => 'differential-review-stage',
        'id'    => 'differential-review-stage',
      ),
      $output);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($content);

    return $object_box;
  }

  /**
   * Render the "Undo" markup for the inline comment undo feature.
   */
  private function renderUndoTemplates() {
    $link = javelin_tag(
      'a',
      array(
        'href'  => '#',
        'sigil' => 'differential-inline-comment-undo',
      ),
      pht('Undo'));

    $div = phutil_tag(
      'div',
      array(
        'class' => 'differential-inline-undo',
      ),
      array('Changes discarded. ', $link));

    return array(
      'l' => phutil_tag('table', array(),
        phutil_tag('tr', array(), array(
          phutil_tag('th', array()),
          phutil_tag('td', array(), $div),
          phutil_tag('th', array()),
          phutil_tag('td', array('colspan' => 3)),
        ))),

      'r' => phutil_tag('table', array(),
        phutil_tag('tr', array(), array(
          phutil_tag('th', array()),
          phutil_tag('td', array()),
          phutil_tag('th', array()),
          phutil_tag('td', array('colspan' => 3), $div),
        ))),
    );
  }

  private function renderViewOptionsDropdown(
    DifferentialChangesetDetailView $detail,
    $ref,
    DifferentialChangeset $changeset) {

    $meta = array();

    $qparams = array(
      'ref'         => $ref,
      'whitespace'  => $this->whitespace,
    );

    if ($this->standaloneURI) {
      $uri = new PhutilURI($this->standaloneURI);
      $uri->setQueryParams($uri->getQueryParams() + $qparams);
      $meta['standaloneURI'] = (string)$uri;
    }

    $repository = $this->repository;
    if ($repository) {
      try {
        $meta['diffusionURI'] =
          (string)$repository->getDiffusionBrowseURIForPath(
            $this->user,
            $changeset->getAbsoluteRepositoryPath($repository, $this->diff),
            idx($changeset->getMetadata(), 'line:first'),
            $this->getBranch());
      } catch (DiffusionSetupException $e) {
        // Ignore
      }
    }

    $change = $changeset->getChangeType();

    if ($this->leftRawFileURI) {
      if ($change != DifferentialChangeType::TYPE_ADD) {
        $uri = new PhutilURI($this->leftRawFileURI);
        $uri->setQueryParams($uri->getQueryParams() + $qparams);
        $meta['leftURI'] = (string)$uri;
      }
    }

    if ($this->rightRawFileURI) {
      if ($change != DifferentialChangeType::TYPE_DELETE &&
          $change != DifferentialChangeType::TYPE_MULTICOPY) {
        $uri = new PhutilURI($this->rightRawFileURI);
        $uri->setQueryParams($uri->getQueryParams() + $qparams);
        $meta['rightURI'] = (string)$uri;
      }
    }

    $user = $this->user;
    if ($user && $repository) {
      $path = ltrim(
        $changeset->getAbsoluteRepositoryPath($repository, $this->diff),
        '/');
      $line = idx($changeset->getMetadata(), 'line:first', 1);
      $callsign = $repository->getCallsign();
      $editor_link = $user->loadEditorLink($path, $line, $callsign);
      if ($editor_link) {
        $meta['editor'] = $editor_link;
      } else {
        $meta['editorConfigure'] = '/settings/panel/display/';
      }
    }

    $meta['containerID'] = $detail->getID();
    $caret = phutil_tag('span', array('class' => 'caret'), '');

    return javelin_tag(
      'a',
      array(
        'class'   => 'button grey small dropdown',
        'meta'    => $meta,
        'href'    => idx($meta, 'detailURI', '#'),
        'target'  => '_blank',
        'sigil'   => 'differential-view-options',
      ),
      array(pht('View Options'), $caret));
  }

}
