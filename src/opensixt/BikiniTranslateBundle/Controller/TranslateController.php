<?php
namespace opensixt\BikiniTranslateBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use opensixt\BikiniTranslateBundle\Services\SearchString;

class TranslateController extends Controller
{
    /**
     * translate index Action
     *
     * @return Response A Response instance
     */
    public function indexAction()
    {
        return $this->render('opensixtBikiniTranslateBundle:Translate:index.html.twig');
    }

    /**
     * edittext Action
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @param string $locale
     * @param int $page
     * @return Response A Response instance
     */
    public function edittextAction($locale, $page = 1)
    {
        $session = $this->get('session');

        // if $locale is not set, redirect to setlocale action
        if (!$locale || $locale == 'empty') {
            // store an attribute for reuse during a later user request
            $session->set('targetRoute', '_translate_edittext');
            return $this->redirect($this->generateUrl('_translate_setlocale'));
        } else {
            // get language id with locale
            $userLang = array_flip($this->getUserLocales());
            $languageId = isset($userLang[$locale]) ? $userLang[$locale] : 0;
        }
        if (!$languageId) {
            $session->set('targetRoute', '_translate_edittext');
            return $this->redirect($this->generateUrl('_translate_setlocale'));
        }

        $request = $this->getRequest();
        $translator = $this->get('translator');

        $editText = $this->get('opensixt_edittext'); // controller intermediate layer
        $currentLangIsCommonLang = $editText->compareCommonAndCurrentLocales($languageId);

        $resources = $this->getUserResources(); // all available resources

        // Update texts with entered values
        if ($request->getMethod() == 'POST') {
            $formData = $request->request->get('form');
            if (isset($formData)) {
                if (isset($formData['action']) && $formData['action'] == 'search') {
                    $page = 1;
                }

                if (isset($formData['action']) && $formData['action'] == 'save') {
                    $textsToSave = array();
                    foreach ($formData as $key => $value) {
                        // for all textareas with name 'text_[number]'
                        if (preg_match("/text_([0-9]+)/", $key, $matches) && strlen($value)) {
                            $textsToSave[$matches[1]] = $value;
                        }
                    }
                    $editText->updateTexts($textsToSave);
                }
            }
        }

        $searchResource = $this->getFieldFromRequest('resource');
        $searchResources = $this->getSearchResources();

        // set search parameters
        $editText->setPaginationPage($page);

        // get search results
        $data = $editText->getData($languageId, $searchResources);

        $formBuilder = $this->createFormBuilder();
        $formBuilder
            ->add('resource', 'choice', array(
                  'label'       => $translator->trans('resource') . ': ',
                  'empty_value' => $translator->trans('all_values'),
                  'choices'     => $resources,
                  'required'    => false,
                  'data'        => $searchResource
                ))
            ->add('action', 'hidden');

        // define textareas for any text
        if (!empty($data['texts'])) {
            foreach ($data['texts'] as $txt) {
                $formBuilder->add('text_' . $txt['id'] , 'textarea', array(
                    'trim' => true,
                    'required' => false,
                ));
            }
        }
        $form = $formBuilder->getForm();

        $templateParam = array(
            'form'                    => $form->createView(),
            'texts'                   => $data['texts'],
            'paginationbar'           => $data['paginationBar'],
            'locale'                  => $locale,
            'currentLangIsCommonLang' => $currentLangIsCommonLang,
        );
        if ($searchResource) {
            $templateParam['resource'] = $searchResource;
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:edittext.html.twig',
            $templateParam
            );
    }

    /**
     * setlocale Action
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @return Response A Response instance
     */
    public function setlocaleAction()
    {
        $session = $this->get('session');

        $locales = $this->getUserLocales();
        if (count($locales) == 1) {
            return $this->redirect($this->generateUrl(
                $session->get('targetRoute') ? : '_translate_home',
                array('locale' => $locales[0])
                ));
        }

        $request = $this->getRequest();
        $translator = $this->get('translator');

        $form = $this->createFormBuilder()
            ->add('locale', 'choice', array(
                    'label'     => $translator->trans('please_choose_locale') . ': ',
                    'empty_value' => '',
                    'choices'   => $locales,
                ))
            ->getForm();

        if ($request->getMethod() == 'POST') {
            // the controller binds the submitted data to the form
            $form->bindRequest($request);

            if ($form->isValid()) {

                if ($form->get('locale')->getData()) {
                    //echo $form->get('locale')->getData();
                    $localeId = $form->get('locale')->getData();
                    $locale = isset($locales[$localeId]) ? $locales[$localeId] : '';

                    return $this->redirect($this->generateUrl(
                        $session->get('targetRoute') ? : '_translate_home',
                        array('locale' => $locale)
                        ));
                }

            } else {
                var_dump($form->getErrors());
            }
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:setlocale.html.twig',
            array(
                'form' => $form->createView(),
            ));
    }

    /**
     * searchstring Action
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @param int $page
     * @return Response A Response instance
     */
    public function searchstringAction($page = 1)
    {
        $translator = $this->get('translator');

        $resources = $this->getUserResources();
        $locales = $this->getUserLocales();
        $mode = array(
            SearchString::SEARCH_EXACT => $translator->trans('exact_match'),
            SearchString::SEARCH_LIKE  => $translator->trans('like'),
        );

        // use tool_language (default language) for search
        $toolLang = $this->container->getParameter('tool_language');

        // retrieve request parameters
        $searchPhrase   = $this->getFieldFromRequest('search');
        $searchResource = $this->getFieldFromRequest('resource');
        $searchMode     = $this->getFieldFromRequest('mode');
        $searchLanguage = $this->getFieldFromRequest('locale');

        $searchResources = $this->getSearchResources();

        if (strlen($searchPhrase) && !empty($searchLanguage)) {
            $searcher = $this->get('opensixt_searchstring');

            // set search parameters
            $searcher->setSearchParameters($searchPhrase, $searchMode);
            $searcher->setLocale($searchLanguage);
            $searcher->setResources($searchResources);
            $searcher->setPaginationPage($page);

            // get search results
            $results = $searcher->getData();
        }

        // set default search language
        $locales_flip = array_flip($locales);
        $preferredChoices = array();
        if (!empty($toolLang) && isset($locales_flip[$toolLang])) {
            $preferredChoices = array($locales_flip[$toolLang]);
        }

        $form = $this->createFormBuilder()
            ->add('search', 'search', array(
                    'label'       => $translator->trans('search_by') . ': ',
                    'trim'        => true,
                    'data'        => $searchPhrase
                ))
            ->add('resource', 'choice', array(
                    'label'       => $translator->trans('with_resource') . ': ',
                    'empty_value' => $translator->trans('all_values'),
                    'choices'     => $resources,
                    'required'    => false,
                    'data'        => $searchResource
                ))
            ->add('locale', 'choice', array(
                    'label'       => $translator->trans('with_language') . ': ',
                    'empty_value' => (!empty($preferredChoices)) ? false : '',
                    'choices'     => $locales,
                    'preferred_choices' => $preferredChoices,
                    'required'    => true,
                    'data'        => $searchLanguage
                ))
            ->add('mode', 'choice', array(
                    'label'       => $translator->trans('search_method') . ': ',
                    'empty_value' => '',
                    'choices'     => $mode,
                    'data'        => $searchMode
                ))
            ->getForm();

        $templateParam = array(
            'form'          => $form->createView(),
            'search'        => urlencode($searchPhrase),
            'searchPhrase'  => $searchPhrase,
            'mode'          => $searchMode,
            'resource'      => $searchResource,
            'locale'        => $searchLanguage,
        );

        if (!empty($results['paginationBar'])) {
            $templateParam['paginationbar'] = $results['paginationBar'];
        }
        if (isset($results['searchResults'])) {
            $templateParam['searchResults'] = $results['searchResults'];
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:searchstring.html.twig',
            $templateParam);
    }

    /**
     * changetext Action
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @param int $page
     * @return Response A Response instance
     */
    public function changetextAction($page)
    {
        $translator = $this->get('translator');

        $resources = $this->getUserResources();
        $locales = $this->getUserLocales();

        // retrieve request parameters
        $searchPhrase = $this->getFieldFromRequest('search');
        $searchLanguage = $this->getFieldFromRequest('locale');
        $searchResource = $this->getFieldFromRequest('resource');

        $searchResources = $this->getSearchResources();

        if (strlen($searchPhrase)) {
            $searcher = $this->get('opensixt_searchstring');

            // set search parameters
            $searcher->setSearchParameters($searchPhrase);
            $searcher->setLocale($searchLanguage);
            $searcher->setResources($searchResources);
            $searcher->setPaginationPage($page);

            // get search results
            $results = $searcher->getData();
        }


        $formBuilder = $this->createFormBuilder();

        // define textareas for any text
        if (!empty($results['searchResults'])){
            foreach ($results['searchResults'] as $txt) {
                $formBuilder->add('text_' . $txt['id'] , 'textarea', array(
                    'trim' => true,
                    'required' => false,
                ));
            }
        }

        $formBuilder->add('search', 'search', array(
                    'label'       => $translator->trans('search_by') . ': ',
                    'trim'        => true,
                    'data'        => $searchPhrase,
                ))
            ->add('resource', 'choice', array(
                    'label'       => $translator->trans('with_resource') . ': ',
                    'empty_value' => $translator->trans('all_values'),
                    'choices'     => $resources,
                    'data'        => $searchResource,
                    'required'    => false,
                ))
            ->add('locale', 'choice', array(
                    'label'       => $translator->trans('with_language') . ': ',
                    'empty_value' => '',
                    'choices'     => $locales,
                    'data'        => $searchLanguage
                ));
        $form = $formBuilder->getForm();

        $templateParam = array(
            'form'          => $form->createView(),
            'search'        => urlencode($searchPhrase),
            'searchPhrase'  => $searchPhrase,
            'locale'        => $searchLanguage,
            'resource'      => $searchResource,
        );

        if (!empty($results['paginationBar'])) {
            $templateParam['paginationbar'] = $results['paginationBar'];
        }
        if (isset($results['searchResults'])) {
            $templateParam['searchResults'] = $results['searchResults'];
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:changetext.html.twig',
            $templateParam
            );
    }

    /**
     * cleantext Action
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @return Response A Response instance
     */
    public function cleantextAction($page)
    {
        $translator = $this->get('translator');

        $resources = $this->getUserResources();
        $locales = $this->getUserLocales();

        // retrieve request parameters
        $searchResource = $this->getFieldFromRequest('resource');
        $searchLanguage = $this->getFieldFromRequest('locale');
        $searchResources = $this->getSearchResources();

        $searcher = $this->get('opensixt_flaggedtext');

        // set search parameters
        $searcher->setLocale($searchLanguage);
        $searcher->setLocales(array_keys($locales));
        $searcher->setResources($searchResources);
        $searcher->setPaginationPage($page);
        $searcher->setExpiredDate(date("Y-m-d"));

        // get search results
        $results = $searcher->getData();

        $form = $this->createFormBuilder()
            ->add('resource', 'choice', array(
                    'label'       => $translator->trans('cleantext_resource') . ': ',
                    'empty_value' => $translator->trans('all_values'),
                    'choices'     => $resources,
                    'required'    => false,
                    'data'        => $searchResource
                ))
            ->add('locale', 'choice', array(
                    'label'       => $translator->trans('cleantext_language') . ': ',
                    'empty_value' => '',
                    'choices'     => $locales,
                    'required'    => false,
                    'data'        => $searchLanguage
                ))
            ->getForm();

        $templateParam = array(
            'form'          => $form->createView(),
            'resource'      => $searchResource,
            'locale'        => $searchLanguage,
        );

        if (!empty($results['paginationBar'])) {
            $templateParam['paginationbar'] = $results['paginationBar'];
        }
        if (isset($results['searchResults'])) {
            $templateParam['searchResults'] = $results['searchResults'];
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:cleantext.html.twig',
            $templateParam
            );
    }

    /**
     * releasetext Action
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @return Response A Response instance
     */
    public function releasetextAction($page)
    {
        $translator = $this->get('translator');

        $resources = $this->getUserResources();
        $locales = $this->getUserLocales();

        // retrieve request parameters
        $searchResource = $this->getFieldFromRequest('resource');
        $searchLanguage = $this->getFieldFromRequest('locale');
        $searchResources = $this->getSearchResources();

        $searcher = $this->get('opensixt_flaggedtext');

        // set search parameters
        $searcher->setLocale($searchLanguage);
        $searcher->setLocales(array_keys($locales));
        $searcher->setResources($searchResources);
        $searcher->setPaginationPage($page);

        // get search results
        $results = $searcher->getData();

        $form = $this->createFormBuilder()
            ->add('resource', 'choice', array(
                    'label'       => $translator->trans('cleantext_resource') . ': ',
                    'empty_value' => $translator->trans('all_values'),
                    'choices'     => $resources,
                    'required'    => false,
                    'data'        => $searchResource
                ))
            ->add('locale', 'choice', array(
                    'label'       => $translator->trans('cleantext_language') . ': ',
                    'empty_value' => '',
                    'choices'     => $locales,
                    'required'    => false,
                    'data'        => $searchLanguage
                ))
            ->getForm();

        $templateParam = array(
            'form'          => $form->createView(),
            'resource'      => $searchResource,
            'locale'        => $searchLanguage,
        );

        if (!empty($results['paginationBar'])) {
            $templateParam['paginationbar'] = $results['paginationBar'];
        }
        if (isset($results['searchResults'])) {
            $templateParam['searchResults'] = $results['searchResults'];
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:releasetext.html.twig',
            $templateParam
            );
    }

    /**
     * copylanguage Action
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @return Response A Response instance
     */
    public function copylanguageAction()
    {
        $translator = $this->get('translator');

        $resources = $this->getUserResources(); // available resources
        $locales = $this->getUserLocales(); // available languages

        // request values
        $lang['from'] = $this->getFieldFromRequest('lang_from');
        $lang['to']   = $this->getFieldFromRequest('lang_to');

        // if set source and destination locale
        if (!empty($lang['from']) && !empty($lang['to'])
                && $lang['from'] != $lang['to']) {

            $copyLang = $this->get('opensixt_copydomain');

            $copyLang->setDomainFrom($lang['from']);
            $copyLang->setDomainTo($lang['to']);
            $copyLang->setResources(array_keys($resources));

            $translationsCount = $copyLang->copyLanguage();
            $translateMade = 'done';
        }

        $form = $this->createFormBuilder()
            ->add('lang_from', 'choice', array(
                    'label'       => $translator->trans('copy_lang_content_from') . ': ',
                    'empty_value' => '',
                    'choices'     => $locales,
                    'required'    => true,
                    'data'        => $lang['from']
                ))
            ->add('lang_to', 'choice', array(
                    'label'       => $translator->trans('copy_lang_content_to') . ': ',
                    'empty_value' => '',
                    'choices'     => $locales,
                    'required'    => true,
                    'data'        => $lang['to']
                ))
            ->getForm();

        $templateParam = array(
            'form'          => $form->createView(),
        );

        if (!empty($translationsCount)) {
            $templateParam['translationsCount'] = $translationsCount;
        }
        if (!empty($translateMade)) {
            $templateParam['translateMade'] = $translateMade;
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:copylanguage.html.twig',
            $templateParam);
    }

    /**
     * copyresource Action
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @return Response A Response instance
     */
    public function copyresourceAction()
    {
        $translator = $this->get('translator');

        $resources = $this->getUserResources(); // available resources
        $locales = $this->getUserLocales(); // available languages

        // request values
        $res['from'] = $this->getFieldFromRequest('res_from');
        $res['to']   = $this->getFieldFromRequest('res_to');
        $lang        = $this->getFieldFromRequest('lang');

        if (!empty($res['from']) && !empty($res['to'])
                && $res['from'] != $res['to']) {
            // if set source and destination locale

            $copyRes = $this->get('opensixt_copydomain');

            $copyRes->setDomainFrom($res['from']);
            $copyRes->setDomainTo($res['to']);
            $copyRes->setResources(array_keys($resources)); // set available resources

            if (!empty($lang)) {
                $arrLang = array($lang);
            } else {
                $arrLang = array_keys($locales);
            }
            $copyRes->setLocales($arrLang);

            $translationsCount = $copyRes->copyResource();
            $translateMade = 'done';
        }

        $form = $this->createFormBuilder()
            ->add('res_from', 'choice', array(
                    'label'       => $translator->trans('copy_res_content_from') . ': ',
                    'empty_value' => '',
                    'choices'     => $resources,
                    'required'    => true,
                    'data'        => $res['from']
                ))
            ->add('res_to', 'choice', array(
                    'label'       => $translator->trans('copy_res_content_to') . ': ',
                    'empty_value' => '',
                    'choices'     => $resources,
                    'required'    => true,
                    'data'        => $res['to']
                ))
            ->add('lang', 'choice', array(
                    'label'       => $translator->trans('copy_res_content_lang') . ': ',
                    'empty_value' => $translator->trans('all_values'),
                    'choices'     => $locales,
                    'required'    => false,
                    'data'        => $lang
                ))
            ->getForm();

        $templateParam = array(
            'form' => $form->createView(),
        );

        if (!empty($translationsCount)) {
            $templateParam['translationsCount'] = $translationsCount;
        }
        if (!empty($translateMade)) {
            $templateParam['translateMade'] = $translateMade;
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:copyresource.html.twig',
            $templateParam);
    }

    /**
     * sendtots Send to translation service
     *
     * @param string $locale
     */
    public function sendtotsAction($locale)
    {
        // if $locale is not set, redirect to setlocale action
        if (!$locale || $locale == 'empty') {
            // store an attribute for reuse during a later user request
            $session->set('targetRoute', '_translate_sendtots');
            return $this->redirect($this->generateUrl('_translate_setlocale'));
        } else {
            // get language id with locale
            $userLang = array_flip($this->getUserLocales());
            $languageId = isset($userLang[$locale]) ? $userLang[$locale] : 0;
        }
        if (!$languageId) {
            $session->set('targetRoute', '_translate_sendtots');
            return $this->redirect($this->generateUrl('_translate_setlocale'));
        }

        $request = $this->getRequest();
        $searcher = $this->get('opensixt_edittext'); // controller intermediate layer

        // set search parameters
        $resources = $this->getUserResources(); // all available resources
        $searcher->setPaginationLimit(0);

        // get search results
        $data = $searcher->getData($languageId, array_keys($resources));

        $form = $this->createFormBuilder()
            ->add('action', 'hidden')
            ->getForm();

        $templateParam = array(
            'form' => $form->createView(),
            'locale' => $locale,
            'data' => $data['texts'],
        );

        // Send data to translation service
        if ($request->getMethod() == 'POST') {
            $formData = $request->request->get('form');
            if ($formData && count($data['texts'])) {
                if (isset($formData['action']) && $formData['action'] == 'send') {
                    $chunks = $searcher->prepareExportData($data['texts']);

                    $export = $this->get('bikini_export');
                    $export->setTargetLanguage($locale);
                    $export->initXliff('human_translation_service');

                    foreach ($chunks as $chunk) {
                        $exportXliff = $export->getDataAsXliff($chunk);
                        $searcher->sendToTS($exportXliff, $chunk);
                    }
                    $templateParam['success'] = 1;
                }
            }
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:sendtots.html.twig',
            $templateParam);
    }

    /**
     * Returns array of locales for logged user
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @return array
     */
    private function getUserLocales()
    {
        $userdata = $this->get('security.context')->getToken()->getUser();
        $locales = $userdata->getUserLanguages();

        foreach ($locales as $locale) {
            $userLang[$locale->getId()] = $locale->getLocale();
        }

        return $userLang;
    }

    /**
     * Returns array of available resources for logged user
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @return array
     */
    private function getUserResources()
    {
        $result = array();
        $userdata = $this->get('security.context')->getToken()->getUser();
        $groups = $userdata->getUserGroups()->toArray();
        foreach ($groups as $grp) {
            $resources = $grp->getResources();
            foreach ($resources as $res) {
                $result[$res->getId()] = $res->getName();
            }
        }

        return $result;
    }

    /**
     * Retrieves a field value from Request by fieldname
     * if it doesn't exist, return empty string
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @param string $fieldName
     * @return mixed
     */
    private function getFieldFromRequest($fieldName)
    {
        $request = $this->getRequest();

        $fieldValue = '';
        if ($request->getMethod() == 'POST') {
            $formData = $request->request->get('form'); // form fields
            if (!empty($formData[$fieldName])) {
                $fieldValue = $formData[$fieldName];
            }
        } elseif ($request->getMethod() == 'GET') {
            if ($request->query->get($fieldName)) {
                $fieldValue = urldecode($request->query->get($fieldName));
            }
        }

        return $fieldValue;
    }

    /**
     * Get search resources
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @return array
     */
    private function getSearchResources()
    {
        // retrieve resource from request
        $searchResource = $this->getFieldFromRequest('resource');

        if (strlen($searchResource)) {
            $searchResources = array($searchResource);
        } else {
            // all available resources
            $resources = $this->getUserResources();
            $searchResources = array_keys($resources);
        }
        return $searchResources;
    }
}