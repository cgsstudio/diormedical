
( function ( blocks, element, blockEditor ) {
    var el            = element.createElement,
        source        = blocks.source,
        useBlockProps = blockEditor.useBlockProps,
		arr           = hipaa_params.forms,
		curl          = hipaa_params.url_connect;
	
	
 function GetForm( props ) {
        var mobileWidth = 1024,
		   query = window.innerWidth > mobileWidth ? 'desktop' : 'mobile',
		   src = curl + props.formID+'?size='+query;
       
	  if( props.formID =='Select') {
		  return;
	  } else {
		
		   return el( 'div', { className: 'hipaa-form', id: props.formID },
					'[hipaatizer id="'+props.formID+'"]'	
			);
		   
	
	  }	
	  geneate_iframe(props.formID);
    }

  
    blocks.registerBlockType( 'hipaatizer/hipaa-form', {
        apiVersion: 2,
        title: 'HIPAA Form',
        icon: 'forms',
        category: 'hipaa-block',

        supports: {
            align: [ 'left', 'center', 'right', 'wide', 'full' ],
        },

		attributes: {
            formID: {
                type: 'string',
                selector: 'select',
            },
        },

        edit: function ( props ) {
            const blockProps = useBlockProps();
            var formID   = props.attributes.formID,
				formType = props.attributes.formType,
                children = [],
				options  = [];


            function setForm( event ) {
                var selected = event.target.querySelector( 'option:checked' );
                props.setAttributes( { formID: selected.value } );
                event.preventDefault();
            }

			options.push(el( 'option', null, 'Select' ));

			arr.forEach(function (_ref) {
                  var id = _ref.id;
                  var name = _ref.name;
                  options.push(el('option', { value: id }, name));
                });

			children.push(el(
				   'label', {  className: 'hipaa-select' }, ( 'Select a Form' ),
                   el('select',  { value: formID,  onChange: setForm },
                    options )

                )
			);



			 if ( formID ) {
                children.push( GetForm( { formID: formID } ) );
            }

            return el(
                'form',
                Object.assign( blockProps, { onSubmit: setForm } ),
                children
            );
        },

        save: function ( props ) {
            var formID = props.attributes.formID;
            if ( ! formID || formID === 'Select' ) return null;
            var blockProps = useBlockProps.save( { className: 'hipaa-form', id: formID } );
            return el( 'div', blockProps, '[hipaatizer id="' + formID + '"]' );
        },

        deprecated: [
            {
                save: function ( props ) {
                    return GetForm( { formID: props.attributes.formID } );
                },
            },
        ],
    } );
	
	
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
