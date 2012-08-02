<?php
namespace opensixt\BikiniTranslateBundle\Services;

use opensixt\BikiniTranslateBundle\Services\HandleText;
use opensixt\BikiniTranslateBundle\Repository\TextRepository;

use opensixt\BikiniTranslateBundle\Helpers\Pagination;

/**
 * CleanText
 * Intermediate layer between Controller and Model (part of controller)
 *
 * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
 */
class CleanText extends HandleText {

    public function __construct($doctrine)
    {
        $this->_paginationLimit = 15;
        parent::__construct($doctrine);
    }

    /**
     * Returns search results and pagination data
     *
     * @author Dmitri Mansilia <dmitri.mansilia@sixt.com>
     * @return array
     */
    public function getData()
    {
        $this->_textRepository->setDate(date("Y-m-d"));

        $data = array();

        // count of all results for the search parameters
        $textCount = $this->_textRepository->getTextCount(
            TextRepository::TASK_SEARCH_FLAGGED_TEXTS,
            $this->_locale,
            $this->_resources);

        // get pagination bar
        $pagination = new Pagination(
            $textCount,
            $this->_paginationLimit,
            $this->_paginationPage);
        $data['paginationBar'] = $pagination->getPaginationBar();

        // get search results
        $data['searchResults'] = $this->_textRepository->getSearchResults(
            $this->_paginationLimit,
            $pagination->getOffset());

        return $data;
    }

}