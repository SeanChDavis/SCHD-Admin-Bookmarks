<?php
/*
Name: Admin Bookmarks
Author: SeanChDavis
Description: Select admin pages to bookmark for quick access via dropdown menu.
Version: 0.2
Requires: 0.1
Class: SCHD_Admin_Bookmarks
Type: Admin
Docs:
License: MIT
License URI: https://opensource.org/licenses/mit
Copyright (c) 2025 SeanChDavis
*/

class SCHD_Admin_Bookmarks extends PM_Plugin
{
    public $title = 'Admin Bookmarks';
    public $type = 'box';
    private int $max_bookmarks = 10;

    /**
     * Returns the site options for Admin Bookmarks.
     */
    public function site_options(): array
    {
        global $motor;

        // Build the options for selecting admin pages
        $path_index = $this->get_admin_page_paths($this->get_admin_pages());

        // Now create the fields for the bookmarks using the options
        $fields = array(
            'bookmark_desc' => array(
                'type' => 'custom',
                'html' => '<p style="max-width:664px">Select up to '.$this->max_bookmarks.' pages to bookmark for quick access in the admin area. Once selected, go to the <a href="'.$motor->admin_url('admin-theme/editor/').'">Admin Template Editor</a> and drag the Admin Bookmarks box to your desired templates. Suggested placement: <code>Body</code> -> <code>Section:Header</code> -> <code>Container:Header</code> -> <em>Placed below the Flex container</em>.</p>'
            )
        );
        for ($i = 1; $i <= $this->max_bookmarks; $i++) {
            $fields["bookmark_{$i}_url"] = array(
                'type'    => 'select',
                'label'   => "Bookmark $i",
                'options' => array_merge(
                    array('' => '-- Select a Page --'),
                    $path_index
                ),
            );
        }

        return $fields;
    }

    /**
     * Get admin pages
     */
    public function get_admin_pages(): array
    {
        global $motor;

        // Build a map of pages with their IDs as keys
        $pages = [];
        foreach ($motor->content->get_where(array('status' => 'live'), true) as $row) {
            $pages[(int) $row['id']] = $row;
        }

        return $pages;
    }

    /**
     * Builds an index of admin page paths to labels.
     */
    public function get_admin_page_paths(array $pages): array
    {
        $path_index = [];
        foreach ($pages as $id => $page) {
            if ( ! empty($page['slug'])) {
                $path              = $this->build_admin_page_path($id);
                $path_index[$path] = $this->build_admin_page_label($id);
            }
        }

        return $path_index;
    }

    /**
     * Builds the path for an admin page based on its ID.
     */
    public function build_admin_page_path(int $id): string
    {
        $pages    = $this->get_admin_pages();
        $segments = [];
        while ($id && isset($pages[$id])) {
            $slug = trim($pages[$id]['slug']);
            if ($slug !== '') {
                array_unshift($segments, $slug);
            }
            $id = (int) $pages[$id]['parent'];
        }

        return implode('/', $segments);
    }

    /**
     * Builds the label for an admin page based on its ID.
     */
    public function build_admin_page_label(int $id): string
    {
        $pages  = $this->get_admin_pages();
        $titles = [];
        while ($id && isset($pages[$id])) {
            $titles[] = $pages[$id]['title'];
            $id       = (int) $pages[$id]['parent'];
        }

        return implode(' -- ', array_reverse($titles));
    }

    /**
     * Gets saved bookmarks and builds list for output.
     */
    public function get_saved_bookmarks(): array
    {
        // Build an index of paths to labels
        $path_index = $this->get_admin_page_paths($this->get_admin_pages());

        // Now build the bookmarks array
        $bookmarks = [];
        for ($i = 1; $i <= $this->max_bookmarks; $i++) {
            $url = trim($this->site_options["bookmark_{$i}_url"] ?? '');
            if ($url && isset($path_index[$url])) {
                $bookmarks[] = ['label' => $path_index[$url], 'url' => $url];
            }
        }

        return $bookmarks;
    }

    /**
     * Outputs the HTML for the admin bookmarks dropdown.
     */
    public function html($depth = 0): void
    {
        global $motor;
        $tab = str_repeat("\t", $depth);

        if (empty($this->get_saved_bookmarks())) {
            return;
        }

        // Output the CSS for the dropdown
        echo "$tab<style>
            .admin-bookmarks-dropdown { position: relative; padding-top: 10px; font-size: 17px; }
            .admin-bookmarks-toggle { color: unset; cursor: pointer; display: flex; align-items: center; column-gap: 5px; width: fit-content; }
            .admin-bookmarks-list { display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid rgba(0, 0, 0, 0.1); margin: 0; padding: 4px 0; list-style: none; z-index: 9999; border-radius: 7px;  }
            .admin-bookmarks-link { display: block; text-decoration: none; color: #333; padding: 4px 22.5px; }
            .admin-bookmarks-link:hover { text-decoration: underline; }
            .admin-bookmarks-dropdown.open .admin-bookmarks-list { display: block; }
        </style>\n";

        // Output the dropdown HTML
        echo "$tab<div class=\"admin-bookmarks-dropdown\">\n";
        echo "$tab\t<a class=\"admin-bookmarks-toggle\">".$motor->tools->svg->icon('menu',
                $depth + 1)."Bookmarks</a>\n";
        echo "$tab\t<ul class=\"admin-bookmarks-list\">\n";

        foreach ($this->get_saved_bookmarks() as $bookmark) {
            echo "$tab\t\t<li class='admin-bookmarks-item'><a href=".$motor->admin_url($bookmark['url'])." class='admin-bookmarks-link'>".$bookmark['label']."</a></li>\n";
        }

        echo "$tab\t</ul>\n";
        echo "$tab</div>\n";

        // Simple JS for dropdown toggle
        echo "$tab<script>document.addEventListener('DOMContentLoaded', function() {
            const btn = document.querySelector('.admin-bookmarks-dropdown a');
            const menu = document.querySelector('.admin-bookmarks-dropdown ul');
            if (btn && menu) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
                });
                document.addEventListener('click', function(e) {
                    if (!btn.contains(e.target) && !menu.contains(e.target)) {
                        menu.style.display = 'none';
                    }
                });
            }
        });</script>\n";
    }
}
