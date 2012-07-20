<?php

namespace opensixt\BikiniTranslateBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use opensixt\BikiniTranslateBundle\Helpers\Pagination;
use opensixt\BikiniTranslateBundle\Repository\TextRepository;

class TranslateController extends Controller
{

    /**
     * Pagination limit
     * @var int
     */
    private $_paginationLimit;

    /**
     * Pagination limit for searchstring
     * @var int
     */
    private $_paginationLimitSearch;


    public function __construct() {
        $this->_paginationLimit = 15;
        $this->_paginationLimitSearch = 15;
    }

    /**
     * translate index Action
     *
     * @return Response A Response instance
     */
    public function indexAction()
    {
        //print_r($this->getLocaleForLogedUser());
        return $this->render('opensixtBikiniTranslateBundle:Translate:index.html.twig');
    }

    /**
     * edittext Action
     *
     * @param string $locale
     * @param array $resources
     * @param int $page
     * @return Response A Response instance
     */
    public function edittextAction($locale, $resources = 0, $page = 1)
    {
        $session = $this->get('session');
        $request = $this->getRequest();

        if (!$locale || $locale == 'empty') {
            // if $locale is not set, redirect to setlocale action

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

        $em = $this->getDoctrine()->getEntityManager();
        $tr = $em->getRepository('opensixtBikiniTranslateBundle:Text');

        $commonLang = $this->container->getParameter('common_language');
        $tr->setCommonLanguage($commonLang);

        $currentLangIsCommonLang = false;
        if ($commonLang == $locale) {
            $currentLangIsCommonLang = true;
        }

        // Update texts with entered values
        if ($request->getMethod() == 'POST') {
            $formData = $request->request->get('form');

            if (isset($formData)) {
                foreach ($formData as $key => $value) {
                    if (preg_match("/text_([0-9]+)/", $key, $matches) && strlen($value)) {
                        $tr->updateText($matches[1], $value);
                    }
                }
            }
        }

        if (!$resources) {
            $resources = array_keys($this->getUserResources()); // available resources
        }

        $textCount = $tr->getTextCount(
            TextRepository::MISSING_TRANS_BY_LANG,
            $languageId,
            $resources);

        $pagination = new Pagination($textCount, $this->_paginationLimit, $page);
        $paginationBar = $pagination->getPaginationBar();

        $texts = $tr->getMissingTranslations(
            $this->_paginationLimit,
            $pagination->getOffset()
            );

        // define textareas for any text
        $formBuilder = $this->createFormBuilder();
        foreach ($texts as $txt) {
            $formBuilder->add('text_' . $txt['id'] , 'textarea', array(
                'trim' => true,
                'required' => false,
            ));
        }
        $form = $formBuilder->getForm();

        return $this->render('opensixtBikiniTranslateBundle:Translate:edittext.html.twig',
            array(
                'form' => $form->createView(),
                'texts' => $texts,
                'paginationbar' => $paginationBar,
                'locale' => $locale,
                'currentLangIsCommonLang' => $currentLangIsCommonLang,
            ));
    }

    /**
     * setlocale Action
     *
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
     * @param int $page
     * @return Response A Response instance
     */
    public function searchstringAction($page = 1)
    {
        $request    = $this->getRequest();
        $translator = $this->get('translator');

        $resources = $this->getUserResources();
        $locales = $this->getUserLocales();
        $mode = array(
            TextRepository::SEARCH_EXACT => $translator->trans('exact_match'),
            TextRepository::SEARCH_LIKE  => $translator->trans('like'),
        );

        $searchPhrase = '';
        $searchResource = '';
        $searchMode = '';

        if ($request->getMethod() == 'POST') {
            $formData = $request->request->get('form'); // form fields
            if (!empty($formData['searchPhrase']) && !empty($formData['searchMode'])) {
                $searchPhrase = $formData['searchPhrase'];
                $searchMode = $formData['searchMode'];
            }
        } elseif ($request->getMethod() == 'GET') {
            if ($request->query->get('search') && $request->query->get('mode')) {
                $searchPhrase = urldecode($request->query->get('search'));
                $searchMode = $request->query->get('mode');
            }
        }

        if (strlen($searchPhrase)) {
            $em = $this->getDoctrine()->getEntityManager();
            $tr = $em->getRepository('opensixtBikiniTranslateBundle:Text');

            // use tool_language (default language) for search
            $toolLang = $this->container->getParameter('tool_language');

            if (!empty($formData['resource'])) {
                $searchResources = array($formData['resource']);
                $searchResource = $formData['resource'];
            } elseif ($request->query->get('resource')) {
                $searchResources = array($request->query->get('resource'));
                $searchResource = $request->query->get('resource');
            } else {
                // all available resources
                $searchResources = array_keys($resources);
            }

            $tr->setSearchParameters(
                $searchPhrase,
                $searchMode);

            $textCount = $tr->getTextCount(
                TextRepository::SEARCH_PHRASE_BY_LANG,
                $tr->getIdByLocale($toolLang),
                $searchResources);

            $pagination = new Pagination(
                $textCount,
                $this->_paginationLimitSearch,
                $page);
            $paginationBar = $pagination->getPaginationBar();

            // get search results
            $searchResults = $tr->getSearchResults(
                $this->_paginationLimitSearch,
                $pagination->getOffset());
            //print_r($searchResults);
        }

        $form = $this->createFormBuilder()
            ->add('searchPhrase', 'search', array(
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
            ->add('searchMode', 'choice', array(
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
        );

        if (isset($paginationBar)) {
            $templateParam['paginationbar'] = $paginationBar;
        }

        if (isset($searchResults)) {
            $templateParam['searchResults'] = $searchResults;
        }

        return $this->render('opensixtBikiniTranslateBundle:Translate:searchstring.html.twig',
            $templateParam);
    }

    /**
     * changetext Action
     *
     * @return Response A Response instance
     */
    public function changetextAction()
    {
        $translator = $this->get('translator');

        $resources = $this->getUserResources();
        $locales = $this->getUserLocales();

        $form = $this->createFormBuilder()
            ->add('search', 'text', array(
                    'label'       => $translator->trans('search_by') . ': ',
                    'trim'        => true,
                ))
            ->add('resource', 'choice', array(
                    'label'       => $translator->trans('with_resource') . ': ',
                    'empty_value' => $translator->trans('all_values'),
                    'choices'     => $resources,
                    'required'    => false,
                ))
            ->add('locale', 'choice', array(
                    'label'       => $translator->trans('with_language') . ': ',
                    'empty_value' => '',
                    'choices'     => $locales,
                ))
            ->getForm();

        return $this->render('opensixtBikiniTranslateBundle:Translate:changetext.html.twig',
            array(
                'form' => $form->createView(),
            ));
    }

    /**
     * Returns array of locales for logged user
     *
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

}
