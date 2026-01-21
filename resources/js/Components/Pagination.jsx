import React from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';

export default function Pagination({ currentPage, totalPages, route }) {
    const generatePageLinks = () => {
        const links = [];
        const range = 2; // Number of pages to show before and after current page

        // Always show first page
        links.push(1);

        // Show left ellipsis if current page is far from first page
        if (currentPage > range + 2) {
            links.push('left-ellipsis');
        }

        // Show pages around current page
        for (
            let i = Math.max(2, currentPage - range); 
            i <= Math.min(totalPages - 1, currentPage + range); 
            i++
        ) {
            if (!links.includes(i)) {
                links.push(i);
            }
        }

        // Show right ellipsis if current page is far from last page
        if (currentPage < totalPages - range - 1) {
            links.push('right-ellipsis');
        }

        // Always show last page
        if (totalPages > 1) {
            links.push(totalPages);
        }

        return links;
    };

    const pageLinks = generatePageLinks();

    return (
        <div className="flex justify-center space-x-2 mt-4">
            {currentPage > 1 && (
                <Link href={route} data={{ page: currentPage - 1 }}>
                    <Button variant="outline" size="sm">
                        Previous
                    </Button>
                </Link>
            )}

            {pageLinks.map((page, index) => {
                if (page === 'left-ellipsis' || page === 'right-ellipsis') {
                    return (
                        <span key={`ellipsis-${index}`} className="px-2 py-1">
                            ...
                        </span>
                    );
                }

                return (
                    <Link 
                        key={page} 
                        href={route} 
                        data={{ page }}
                    >
                        <Button 
                            variant={page === currentPage ? 'default' : 'outline'} 
                            size="sm"
                        >
                            {page}
                        </Button>
                    </Link>
                );
            })}

            {currentPage < totalPages && (
                <Link href={route} data={{ page: currentPage + 1 }}>
                    <Button variant="outline" size="sm">
                        Next
                    </Button>
                </Link>
            )}
        </div>
    );
}