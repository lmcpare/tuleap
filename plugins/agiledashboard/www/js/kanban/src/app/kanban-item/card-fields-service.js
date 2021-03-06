(function () {
    angular
        .module('kanban')
        .service('CardFieldsService', CardFieldsService);

    CardFieldsService.$inject = ['$sce', '$filter'];

    function CardFieldsService($sce, $filter) {
        var highlight = $filter('highlight');

        return {
            cardFieldIsSimpleValue      : cardFieldIsSimpleValue,
            cardFieldIsList             : cardFieldIsList,
            cardFieldIsText             : cardFieldIsText,
            cardFieldIsDate             : cardFieldIsDate,
            cardFieldIsFile             : cardFieldIsFile,
            cardFieldIsCross            : cardFieldIsCross,
            cardFieldIsPermissions      : cardFieldIsPermissions,
            cardFieldIsUser             : cardFieldIsUser,
            getCardFieldListValues      : getCardFieldListValues,
            getCardFieldTextValue       : getCardFieldTextValue,
            getCardFieldFileValue       : getCardFieldFileValue,
            getCardFieldCrossValue      : getCardFieldCrossValue,
            getCardFieldPermissionsValue: getCardFieldPermissionsValue,
            getCardFieldUserValue       : getCardFieldUserValue
        };

        function cardFieldIsSimpleValue(type) {
            switch (type) {
                case 'string':
                case 'int':
                case 'float':
                case 'aid':
                case 'atid':
                case 'computed':
                case 'priority':
                    return true;
            }
        }

        function cardFieldIsList(type) {
            switch (type) {
                case 'sb':
                case 'msb':
                case 'rb':
                case 'cb':
                case 'tbl':
                case 'shared':
                    return true;
            }
        }

        function cardFieldIsDate(type) {
            switch (type) {
                case 'date':
                case 'lud':
                case 'subon':
                    return true;
            }
        }

        function cardFieldIsText(type) {
            return type == 'text';
        }

        function cardFieldIsFile(type) {
            return type == 'file';
        }

        function cardFieldIsCross(type) {
            return type == 'cross';
        }

        function cardFieldIsPermissions(type) {
            return type == 'perm';
        }

        function cardFieldIsUser(type) {
            return type == 'subby';
        }

        function getCardFieldListValues(values, filter_terms) {
            function getValueRendered(value) {
                if (value.color) {
                    return getValueRenderedWithColor(value, filter_terms);
                } else if (value.avatar_url) {
                    return getCardFieldUserValue(value, filter_terms);
                }

                return highlight(value.label, filter_terms);
            }

            function getValueRenderedWithColor(value, filter_terms) {
                var rgb   = 'rgb(' + value.color.r + ', ' + value.color.g + ', ' + value.color.b + ')',
                    color = '<span class="color" style="background: ' + rgb + '"></span>';

                return color + highlight(value.label, filter_terms);
            }

            return $sce.trustAsHtml(_.map(values, getValueRendered).join(', '));
        }

        function getCardFieldTextValue(value) {
            return $sce.trustAsHtml(value);
        }

        function getCardFieldFileValue(artifact_id, field_id, file_descriptions, filter_terms) {
            function getFileUrl(file) {
                return '/plugins/tracker/?aid=' + artifact_id + '&field=' + field_id + '&func=show-attachment&attachment=' + file.id;
            }

            function getFileLink(file) {
                var file_name = highlight(file.name, filter_terms);

                return '<a data-nodrag="true" href="' + getFileUrl(file) + '" title="'+ $sce.getTrustedHtml(file.description) +'"><i class="icon-file-text-alt"></i> ' + file_name + '</a>';
            }

            return $sce.trustAsHtml(_.map(file_descriptions, getFileLink).join(', '));
        }

        function getCardFieldCrossValue(links, filter_terms) {
            function getCrossLink(link) {
                return $sce.trustAsHtml('<a data-nodrag="true" href="' + link.url + '">' + highlight(link.ref, filter_terms) + '</a>');
            }

            return $sce.trustAsHtml(_.map(links, getCrossLink).join(', '));
        }

        function getCardFieldPermissionsValue(values) {
            return _(values).join(', ');
        }

        function getCardFieldUserValue(value, filter_terms) {
            var avatar       = '<div class="avatar"><img src="' + value.avatar_url + '" /></div> ',
                display_name = highlight(value.display_name, filter_terms),
                link         = '<a data-nodrag="true" href="' + value.user_url +'">'+ avatar + display_name +'</a>';

            return $sce.trustAsHtml('<div class="user">' + link +'</div>');
        }
    }
})();
