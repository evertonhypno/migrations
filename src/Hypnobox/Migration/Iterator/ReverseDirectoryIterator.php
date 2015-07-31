<?php

namespace Hypnobox\Iterator;

class ReverseDirectoryIterator extends \SplMaxHeap
{
    public function __construct(\DirectoryIterator $directoryIterator)
    {
        foreach ($directoryIterator as $file) {
            $this->insert($file);
        }
    }
    
    /**
     * 
     * @param \SplFileInfo $value1
     * @param \SplFileInfo $value2
     */
    protected function compare($value1, $value2)
    {
        switch (true) {
            case $value1->getPathname() > $value2->getPathname():
                return 1;
            case $value1->getPathname() < $value2->getPathname():
                return -1;
            default:
                return 0;
        }
    }
}
