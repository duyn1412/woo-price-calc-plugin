(function(apiFetch){
    console.log('🔍 Modules Query Filters: Script loaded');
  
    const REST = {
      path: '/wp/v2/modules',
      nonce: window.wp?.apiFetch?.nonce || ''
    };
  
    function q(selector, ctx=document){ return ctx.querySelector(selector); }
    function qa(selector, ctx=document){ return Array.from(ctx.querySelectorAll(selector)); }
  
    // Find the target element by anchor (id="<anchor>")
    function getTargetEl(anchor){ 
      console.log('🔍 Looking for element with ID:', anchor);
      const element = document.getElementById(anchor);
      if (element) {
        console.log('✅ Found target element:', element);
        return element;
      } else {
        console.log('❌ Target element not found. Available elements with IDs:');
        const allElementsWithIds = document.querySelectorAll('[id]');
        allElementsWithIds.forEach(el => {
          if (el.id.includes('loop') || el.id.includes('module') || el.id.includes('query')) {
            console.log('  - ID:', el.id, 'Element:', el);
          }
        });
        return null;
      }
    }
    
    // Create target element if it doesn't exist
    function createTargetElement(anchor){
      console.log('🔨 Creating target element with ID:', anchor);
      
      // Create the main container
      const targetEl = document.createElement('div');
      targetEl.id = anchor;
      targetEl.className = 'modules-results-container';
      
      // Find a good place to insert it (after the form)
      const form = document.querySelector('form.modules-filters');
      if (form) {
        // Insert after the form
        form.parentNode.insertBefore(targetEl, form.nextSibling);
        console.log('✅ Target element created and inserted after form');
      } else {
        // Fallback: append to body
        document.body.appendChild(targetEl);
        console.log('✅ Target element created and appended to body');
      }
      
      return targetEl;
    }
    
    function buildDefaults(form){
      return {
        anchor: form.dataset.anchor,
        query: {
          postType: 'modules', // Use modules post type
          perPage: 8, // Fixed 8 items per page
          orderBy: (form.dataset.orderby || 'date'),
          order: (form.dataset.order || 'desc').toLowerCase(), // Keep lowercase
          page: 1,
          inherit: false
        }
      };
    }
    
    function buildAttributes(form){
      console.log('🔧 Building attributes from form:', form);
      console.log('📋 Form dataset:', {
        anchor: form.dataset.anchor,
        taxonomy: form.dataset.taxonomy,
        postType: form.dataset.postType,
        perPage: form.dataset.perPage,
        orderby: form.dataset.orderby,
        order: form.dataset.order
      });
      
      const fd = new FormData(form);
      console.log('📋 FormData entries:');
      for (let [key, value] of fd.entries()) {
        console.log(`  ${key}: ${value}`);
      }
      
      const attrs = buildDefaults(form);
      
      console.log('📋 Form data entries:');
      for (let [key, value] of fd.entries()) {
        console.log(`  ${key}: ${value}`);
      }
      
      // search
      const search = fd.get('s');
      if (search) {
        attrs.query.search = search;
        console.log('🔍 Search:', search);
      }
      
      // orderby
      const orderby = fd.get('orderby');
      if (orderby) {
        attrs.query.orderBy = orderby;
        console.log('📊 OrderBy:', orderby);
      }
      
      // order
      const order = fd.get('order');
      if (order) {
        attrs.query.order = order.toUpperCase();
        console.log('📊 Order:', order.toUpperCase());
      }
  
      // taxonomy
      const taxonomy = form.dataset.taxonomy || 'industry';
      const terms = getSelectedTerms(form);
      console.log('🏷️ Taxonomy:', taxonomy);
      console.log('🏷️ Terms:', terms);
      console.log('🏷️ Terms length:', terms.length);
      
      if (terms.length){
        attrs.query.taxQuery = [{
          taxonomy,
          terms,
          field: 'term_id',
          operator: 'IN',
          includeChildren: true
        }];
        console.log('🏷️ Tax query added:', attrs.query.taxQuery);
      } else {
        console.log('🏷️ No terms selected, no tax query');
      }
  
      console.log('🔧 Final attributes:', attrs);
      return attrs;
    }
  
    async function renderQuery(attributes){
      console.log('🌐 API Request data:', attributes);
      
      try {
        // Use /wp/v2/modules API with proper parameters
        const query = attributes.query;
        const postType = query.postType || 'modules';
        const currentPage = query.page || 1;
        const perPage = 8; // Fixed 8 items per page
        
        // Build API URL with parameters
        let url = `/wp/v2/${postType}?per_page=${perPage}&page=${currentPage}&orderby=${query.orderBy || 'date'}`;
        
        // Fix order parameter - ensure lowercase
        const order = (query.order || 'desc').toLowerCase();
        url += `&order=${order}`;
        
        // Add taxonomy filter if exists
        if (query.taxQuery && query.taxQuery.length > 0) {
          const taxQuery = query.taxQuery[0];
          console.log('🏷️ Adding taxonomy filter:', taxQuery);
          url += `&${taxQuery.taxonomy}=${taxQuery.terms.join(',')}`;
          console.log('🏷️ URL after taxonomy:', url);
        } else {
          console.log('🏷️ No taxonomy filter to add');
        }
        
        // Add search if exists
        if (query.search) {
          console.log('🔍 Adding search filter:', query.search);
          url += `&search=${encodeURIComponent(query.search)}`;
          console.log('🔍 URL after search:', url);
        } else {
          console.log('🔍 No search filter to add');
        }
        
        console.log('🌐 Final API URL:', url);
        
        // Get posts from API
        const res = await apiFetch({
          path: url.replace(/^\//,''),
          method: 'GET',
          headers: { 'X-WP-Nonce': REST.nonce }
        });
        
        console.log('📡 API Response:', res);
        
        // Try to get pagination info from response headers
        let totalPages = null;
        let totalPosts = null;
        
        try {
          // Check if response has pagination headers
          const response = await fetch(url, {
            method: 'GET',
            headers: { 'X-WP-Nonce': REST.nonce }
          });
          
          const xWpTotal = response.headers.get('X-WP-Total');
          const xWpTotalPages = response.headers.get('X-WP-TotalPages');
          
          if (xWpTotal) totalPosts = parseInt(xWpTotal);
          if (xWpTotalPages) totalPages = parseInt(xWpTotalPages);
          
          console.log('📄 API Pagination Headers:', {
            'X-WP-Total': xWpTotal,
            'X-WP-TotalPages': xWpTotalPages,
            totalPosts,
            totalPages
          });
        } catch (error) {
          console.log('⚠️ Could not get pagination headers:', error);
        }
        
        // Build simple results HTML
        return await buildSimpleResults(res, attributes, currentPage, perPage, totalPages, totalPosts);
        
      } catch (error) {
        console.error('❌ Query rendering error:', error);
        return '';
      }
    }

    async function buildSimpleResults(posts, attributes, currentPage, perPage, totalPagesFromAPI = null, totalPostsFromAPI = null){
      if (!posts || !Array.isArray(posts)) {
        console.log('📄 No posts to render');
        return '';
      }
      
      console.log('📄 Building simple results with', posts.length, 'posts, page', currentPage);
      
      // Debug: Log first post structure
      if (posts.length > 0) {
        console.log('📋 First post structure:', posts[0]);
        console.log('📋 Post title:', posts[0].title);
        console.log('📋 Post excerpt:', posts[0].excerpt);
        console.log('📋 Post link:', posts[0].link);
        console.log('📋 Post date:', posts[0].date);
      }
      
      // Use API headers if available, otherwise estimate
      let totalPages = totalPagesFromAPI;
      let totalPosts = totalPostsFromAPI;
      
      if (!totalPages) {
        // Fallback: estimate based on current page and posts length
        if (posts.length === perPage) {
          // If we got exactly perPage items, assume there are more pages
          totalPages = currentPage + 1; // At least one more page
        } else if (posts.length < perPage) {
          // If we got fewer than perPage items, this is the last page
          totalPages = currentPage;
        } else {
          totalPages = currentPage;
        }
      }
      
      if (!totalPosts) {
        totalPosts = posts.length;
      }
      
      console.log('📄 Pagination calculation:', {
        postsLength: posts.length,
        perPage: perPage,
        currentPage: currentPage,
        totalPages: totalPages,
        totalPosts: totalPosts,
        fromAPI: { totalPagesFromAPI, totalPostsFromAPI }
      });
      
      let html = `
        <div class="modules-results">
          <div class="results-list">
      `;
      
      // Render posts
      posts.forEach((post, index) => {
        // Safe access to post properties
        const title = post.title?.rendered || post.title || 'No Title';
        const excerpt = post.excerpt?.rendered || post.excerpt || 'No excerpt available';
        const link = post.link || '#';
        const date = post.date ? new Date(post.date).toLocaleDateString() : 'No date';
        
        html += `
          <div class="result-item">
            <h4><a href="${link}">${title}</a></h4>
            <div class="excerpt">${excerpt}</div>
            <div class="date">${date}</div>
          </div>
        `;
      });
      
      html += `
          </div>
          
          <div class="pagination">
            <div class="page-info">Page ${currentPage} of ${totalPages}</div>
      `;
      
      // Build pagination with smart page display
      const maxVisiblePages = 5;
      let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
      let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
      
      // Adjust start page if we're near the end
      if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
      }
      
      console.log('📄 Pagination range:', { startPage, endPage, currentPage, totalPages });
      
      // Add Previous button
      if (currentPage > 1) {
        html += `<a class="page-numbers prev" href="#" data-page="${currentPage - 1}">« Previous</a>`;
      }
      
      // Add first page if not in range
      if (startPage > 1) {
        html += `<a class="page-numbers" href="#" data-page="1">1</a>`;
        if (startPage > 2) {
          html += `<span class="page-numbers dots">...</span>`;
        }
      }
      
      // Add page numbers in range
      for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
          html += `<span class="page-numbers current">${i}</span>`;
        } else {
          html += `<a class="page-numbers" href="#" data-page="${i}">${i}</a>`;
        }
      }
      
      // Add last page if not in range
      if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
          html += `<span class="page-numbers dots">...</span>`;
        }
        html += `<a class="page-numbers" href="#" data-page="${totalPages}">${totalPages}</a>`;
      }
      
      // Add Next button
      if (currentPage < totalPages) {
        html += `<a class="page-numbers next" href="#" data-page="${currentPage + 1}">Next »</a>`;
      }
      
      html += `
          </div>
        </div>
      `;
      
      console.log('📄 Built simple results HTML');
      return html;
    }

    function replaceResultsHtml(targetEl, html){
      if (targetEl) {
        console.log('🔄 Replacing target element content with results');
        
        // Replace entire content with new results
        targetEl.innerHTML = html;
        
        // Add event listeners for pagination
        addPaginationListeners(targetEl);
        
        // Re-initialize accordion functionality for any new content
        initAccordion();
        
        console.log('✅ Results replaced successfully');
      } else {
        console.error('❌ Target element not found for replacement');
      }
    }

    function addPaginationListeners(targetEl){
      console.log('🔗 Adding pagination event listeners');
      
      // Add click listeners to pagination links
      const paginationLinks = targetEl.querySelectorAll('.page-numbers[data-page]');
      paginationLinks.forEach(link => {
        link.addEventListener('click', async function(e){
          e.preventDefault();
          
          const page = parseInt(this.getAttribute('data-page'));
          console.log('📄 Pagination clicked, page:', page);
          
          // Get current form data
          const form = document.querySelector('form.modules-filters');
          if (form) {
            // Build attributes with new page
            const attrs = buildAttributes(form);
            attrs.query.page = page;
            
            console.log('🌐 Rendering query for page:', page);
            const html = await renderQuery(attrs);
            
            // Replace content
            replaceResultsHtml(targetEl, html);
          }
        });
      });
      
      console.log('✅ Added', paginationLinks.length, 'pagination listeners');
    }

    async function loadDefaultList(form, anchor){
      console.log('📄 Loading default list for anchor:', anchor);
      
      try {
        // Build default attributes (no filters)
        const attrs = buildDefaults(form);
        console.log('📊 Default attributes:', attrs);
        
        // Render default query
        console.log('🌐 Rendering default query...');
        const html = await renderQuery(attrs);
        console.log('📄 Default query rendered, HTML length:', html.length);
        
        // Create or find target element
        let targetEl = getTargetEl(anchor);
        if (!targetEl) {
          console.log('🔨 Creating new target element with ID:', anchor);
          targetEl = createTargetElement(anchor);
        }
        
        if (targetEl) {
          console.log('✅ Target element ready, loading default content');
          replaceResultsHtml(targetEl, html);
        } else {
          console.error('❌ Could not create or find target element');
        }
        
      } catch (error) {
        console.error('❌ Error loading default list:', error);
      }
    }

    function getSelectedTerms(form){
      const terms = [];
      const checkboxes = form.querySelectorAll('input[type="checkbox"][name="tax_terms[]"]:checked');
      checkboxes.forEach(cb => {
        if (cb.value) {
          terms.push(parseInt(cb.value));
        }
      });
      console.log('🏷️ Selected terms:', terms);
      return terms;
    }
  
    // Initialize accordion functionality for parent terms only
    function initAccordion() {
      console.log('🎛️ Initializing parent term accordion functionality');
      
      // Use event delegation on the form
      const form = document.querySelector('form.modules-filters');
      if (!form) {
        console.error('❌ No form found for event delegation');
        return;
      }
      
      // Remove existing event listener
      form.removeEventListener('click', handleParentClick);
      
      // Add event delegation
      form.addEventListener('click', handleParentClick);
      
      // Initialize icons for existing parents
      const taxParents = document.querySelectorAll('.tax-item-parent');
      console.log('🎛️ Found', taxParents.length, 'parent terms for accordion');
      
      taxParents.forEach((parent, index) => {
        console.log('🎛️ Processing parent', index + 1, ':', parent);
        
        // Add toggle icon if not exists
        let toggle = parent.querySelector('.parent-toggle');
        if (!toggle) {
          toggle = document.createElement('div');
          toggle.className = 'parent-toggle';
          parent.appendChild(toggle);
        }
        
        // Set initial icon based on collapsed state
        updateToggleIcon(parent, toggle);
      });
      
      console.log('✅ Parent term accordion functionality initialized for', taxParents.length, 'parents');
    }
    
    // Handle parent click with event delegation
    function handleParentClick(e) {
      // Only handle clicks on parent text or toggle icon, not on checkboxes
      const parent = e.target.closest('.tax-item-parent');
      if (!parent) return;
      
      // Don't handle clicks on checkboxes or labels
      if (e.target.type === 'checkbox' || e.target.tagName === 'LABEL' || e.target.closest('label')) {
        return;
      }
      
      e.preventDefault();
      console.log('🎛️ Parent clicked, toggling accordion');
      console.log('🎛️ Before toggle - classes:', parent.className);
      
      // Toggle collapsed class
      parent.classList.toggle('collapsed');
      
      const isCollapsed = parent.classList.contains('collapsed');
      console.log('🎛️ After toggle - classes:', parent.className);
      console.log('🎛️ Accordion state:', isCollapsed ? 'collapsed' : 'expanded');
      
      // Update icon
      const toggle = parent.querySelector('.parent-toggle');
      if (toggle) {
        updateToggleIcon(parent, toggle);
      }
    }
    
    // Update toggle icon based on state
    function updateToggleIcon(parent, toggle) {
      const isCollapsed = parent.classList.contains('collapsed');
      toggle.innerHTML = isCollapsed ? `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M6 12H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M12 18V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      ` : `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M6 12H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      `;
    }

    // Show loading state
    function showLoading() {
      console.log('⏳ Showing loading state');
      const resultsContainer = document.querySelector('.modules-results-container');
      if (resultsContainer) {
        resultsContainer.innerHTML = `
          <div class="modules-results">
            <div class="loading-container">
              <div class="loading-spinner"></div>
              <p class="loading-text">Loading modules...</p>
            </div>
          </div>
        `;
      }
    }

    // Hide loading state
    function hideLoading() {
      console.log('✅ Hiding loading state');
      // Loading will be replaced by actual content
    }

    // Initialize auto-submit on checkbox change
    function initAutoSubmit(form, anchor) {
      console.log('🔄 Initializing auto-submit functionality');
      
      // Add change listeners to all checkboxes
      const checkboxes = form.querySelectorAll('input[type="checkbox"]');
      checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', async function() {
          console.log('☑️ Checkbox changed, auto-submitting form');
          
          // Build attributes from form
          const attrs = buildAttributes(form);
          console.log('📊 Built attributes:', attrs);
          
          // Render query
          console.log('🌐 Rendering query...');
          const html = await renderQuery(attrs);
          console.log('📄 Query rendered, HTML length:', html.length);
          
          // Find or create target element
          let targetEl = getTargetEl(anchor);
          if (!targetEl) {
            console.log('🔨 Creating new target element with ID:', anchor);
            targetEl = createTargetElement(anchor);
          }
          
          // Replace content
          if (targetEl) {
            replaceResultsHtml(targetEl, html);
          } else {
            console.error('❌ Could not find or create target element');
          }
        });
      });
      
      console.log('✅ Auto-submit functionality initialized for', checkboxes.length, 'checkboxes');
    }

    // Main initialization
    document.addEventListener('DOMContentLoaded', function(){
      console.log('🚀 DOM Content Loaded - Initializing Modules Filters');
      
      const form = q('form.modules-filters');
      if (!form) {
        console.error('❌ No modules filter form found');
        return;
      }
      
      const anchor = form.dataset.anchor || 'modules-results';
      console.log('🎯 Target anchor:', anchor);
      
      // Initialize accordion functionality
      initAccordion();
      
      // Add auto-submit on checkbox change
      initAutoSubmit(form, anchor);
      
      // Load default list on page load
      loadDefaultList(form, anchor);
      
      // Form submit handler
      form.addEventListener('submit', async function(e){
        e.preventDefault();
        console.log('📝 Form submitted');
        
        // Build attributes from form
        const attrs = buildAttributes(form);
        console.log('📊 Built attributes:', attrs);
        
        // Show loading state
        showLoading();
        
        // Render query
        console.log('🌐 Rendering query...');
        const html = await renderQuery(attrs);
        console.log('📄 Query rendered, HTML length:', html.length);
        
        // Find or create target element
        let targetEl = getTargetEl(anchor);
        if (!targetEl) {
          console.log('🔨 Creating new target element with ID:', anchor);
          targetEl = createTargetElement(anchor);
        }
        
        // Replace content
        if (targetEl) {
          replaceResultsHtml(targetEl, html);
        } else {
          console.error('❌ Could not find or create target element');
        }
      });
      
      // Clear button handler
      const clearBtn = form.querySelector('button.clear-button');
      if (clearBtn) {
        clearBtn.addEventListener('click', async function(e){
          e.preventDefault();
          console.log('🔄 Clear button clicked');
          
          // Uncheck all checkboxes
          const checkboxes = form.querySelectorAll('input[type="checkbox"]');
          checkboxes.forEach(checkbox => {
            checkbox.checked = false;
          });
          
          // Clear search input
          const searchInput = form.querySelector('input[type="text"]');
          if (searchInput) {
            searchInput.value = '';
          }
          
          // Load default list
          loadDefaultList(form, anchor);
        });
      }
      
      console.log('✅ Modules Filters initialized successfully');
    });
  
  })(window.wp?.apiFetch || window.apiFetch);